<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\PayoutStatus;
use App\Models\Payout;
use App\Models\User;
use App\Notifications\PayoutOutcomeNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class FailPayout
{
    public function __construct(private readonly WriteAuditLog $auditLog) {}

    public function handle(User $actor, Payout $payout): Payout
    {
        $payout = DB::transaction(function () use ($actor, $payout): Payout {
            $payout = Payout::query()->lockForUpdate()->findOrFail($payout->getKey());
            Gate::forUser($actor)->authorize('markFailed', $payout);
            $commissions = $payout->commissions()->orderBy('id')->lockForUpdate()->get();

            foreach ($commissions as $commission) {
                $commission->update(['payout_id' => null]);
                $this->auditLog->handle($actor, $commission, 'commission.payout_released', ['payout_id' => $payout->getKey()], ['payout_id' => null]);
            }

            $payout->forceFill(['status' => PayoutStatus::Failed])->save();
            $this->auditLog->handle($actor, $payout, 'payout.failed', ['status' => PayoutStatus::Processing->value], [
                'status' => PayoutStatus::Failed->value,
                'commission_ids' => $commissions->modelKeys(),
            ]);

            return $payout;
        }, 3);

        $payout->affiliate->user->notify(new PayoutOutcomeNotification($payout));

        return $payout;
    }
}
