<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CommissionStatus;
use App\Enums\PayoutStatus;
use App\Models\Commission;
use App\Models\Payout;
use App\Models\User;
use App\Notifications\PayoutOutcomeNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class MarkPayoutPaid
{
    public function __construct(private readonly WriteAuditLog $auditLog) {}

    public function handle(User $actor, Payout $payout, UploadedFile|string $proof): Payout
    {
        Gate::forUser($actor)->authorize('markPaid', $payout);
        $createdFile = $proof instanceof UploadedFile;
        if ($createdFile) {
            Validator::make(['proof' => $proof], ['proof' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120']])->validate();
            $path = $proof->store('payout-proofs', 'local');
            if ($path === false) {
                throw ValidationException::withMessages(['proof' => __('payouts.proof_failed')]);
            }
        } else {
            $path = $proof;
            if (! preg_match('~\Apayout-proofs/[A-Za-z0-9_-]+\.(pdf|jpe?g|png)\z~i', $path) ||
                ! Storage::disk('local')->exists($path) ||
                ! in_array(Storage::disk('local')->mimeType($path), ['application/pdf', 'image/jpeg', 'image/png'], true) ||
                Storage::disk('local')->size($path) > 5 * 1024 * 1024 ||
                Payout::query()->where('proof_path', $path)->exists()) {
                throw ValidationException::withMessages(['proof' => __('payouts.proof_failed')]);
            }
        }

        try {
            $payout = DB::transaction(function () use ($actor, $payout, $path): Payout {
                $payout = Payout::query()->lockForUpdate()->findOrFail($payout->getKey());
                Gate::forUser($actor)->authorize('markPaid', $payout);
                $commissions = $payout->commissions()->orderBy('id')->lockForUpdate()->get();

                if ($commissions->isEmpty() ||
                    $commissions->contains(fn (Commission $commission): bool => $commission->status !== CommissionStatus::Approved || $commission->affiliate_id !== $payout->affiliate_id) ||
                    $commissions->sum('amount') !== $payout->total_amount) {
                    throw ValidationException::withMessages(['status' => __('payouts.invalid_transition')]);
                }

                foreach ($commissions as $commission) {
                    $commission->forceFill(['status' => CommissionStatus::Paid])->save();
                    $this->auditLog->handle($actor, $commission, 'commission.paid', ['status' => CommissionStatus::Approved->value], ['status' => CommissionStatus::Paid->value]);
                }

                $payout->forceFill(['status' => PayoutStatus::Paid, 'paid_at' => now(), 'proof_path' => $path])->save();
                $this->auditLog->handle($actor, $payout, 'payout.paid', ['status' => PayoutStatus::Processing->value], [
                    'status' => PayoutStatus::Paid->value,
                    'proof_path' => $path,
                    'commission_ids' => $commissions->modelKeys(),
                ]);

                return $payout;
            }, 3);
        } catch (\Throwable $exception) {
            if ($createdFile) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }

        $payout->affiliate->user->notify(new PayoutOutcomeNotification($payout));

        return $payout;
    }
}
