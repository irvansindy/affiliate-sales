<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CommissionStatus;
use App\Enums\TransactionStatus;
use App\Models\Commission;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class ChangeCommissionStatus
{
    public function __construct(private readonly WriteAuditLog $auditLog) {}

    public function handle(User $actor, Commission $commission, CommissionStatus $target): Commission
    {
        if (! in_array($target, [CommissionStatus::Approved, CommissionStatus::Rejected], true)) {
            throw ValidationException::withMessages(['status' => __('commissions.invalid_transition')]);
        }

        return DB::transaction(function () use ($actor, $commission, $target): Commission {
            $transaction = Transaction::query()->lockForUpdate()->findOrFail($commission->transaction_id);
            $record = Commission::query()->lockForUpdate()->findOrFail($commission->getKey());

            Gate::forUser($actor)->authorize($target === CommissionStatus::Approved ? 'approve' : 'reject', $record);

            if ($record->status === $target) {
                return $record;
            }

            if ($record->status !== CommissionStatus::Pending || $transaction->status !== TransactionStatus::Paid) {
                throw ValidationException::withMessages(['status' => __('commissions.invalid_transition')]);
            }

            if ($target === CommissionStatus::Approved && ($record->approvable_at === null || $record->approvable_at->isFuture())) {
                throw ValidationException::withMessages(['status' => __('commissions.not_ready')]);
            }

            $record->forceFill([
                'status' => $target,
                'approved_at' => $target === CommissionStatus::Approved ? now() : null,
                'ready_for_review_at' => $target === CommissionStatus::Approved
                    ? ($record->ready_for_review_at ?? now())
                    : $record->ready_for_review_at,
            ])->save();

            $this->auditLog->handle($actor, $record, 'commission.'.$target->value, ['status' => CommissionStatus::Pending->value], [
                'status' => $target->value,
                'approved_at' => $record->approved_at?->toIso8601String(),
            ]);

            return $record;
        });
    }
}
