<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CommissionStatus;
use App\Enums\PayoutStatus;
use App\Models\Affiliate;
use App\Models\Commission;
use App\Models\Payout;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class CreatePayout
{
    public function __construct(private readonly WriteAuditLog $auditLog) {}

    /** @param list<int> $commissionIds */
    public function handle(User $actor, Affiliate $affiliate, array $commissionIds): Payout
    {
        Gate::forUser($actor)->authorize('create', Payout::class);

        return DB::transaction(function () use ($actor, $affiliate, $commissionIds): Payout {
            $affiliate = Affiliate::query()->lockForUpdate()->findOrFail($affiliate->getKey());

            if (blank($affiliate->bank_name) || blank($affiliate->bank_account_name) || blank($affiliate->bank_account_number)) {
                throw ValidationException::withMessages(['affiliate_id' => __('payouts.bank_required')]);
            }

            $ids = array_values(array_unique(array_map('intval', $commissionIds)));
            if ($ids === [] || count($ids) !== count($commissionIds)) {
                throw ValidationException::withMessages(['commissions' => __('payouts.invalid_commissions')]);
            }

            $commissions = Commission::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            if ($commissions->count() !== count($ids) || $commissions->contains(fn (Commission $commission): bool => $commission->affiliate_id !== $affiliate->getKey() ||
                $commission->status !== CommissionStatus::Approved ||
                $commission->payout_id !== null)) {
                throw ValidationException::withMessages(['commissions' => __('payouts.invalid_commissions')]);
            }

            $total = $commissions->sum('amount');
            $minimum = (int) Setting::get('min_payout', '100000');
            if ($total < $minimum) {
                throw ValidationException::withMessages(['commissions' => __('payouts.below_minimum', ['amount' => number_format($minimum, 0, ',', '.')])]);
            }

            $payout = Payout::create([
                'affiliate_id' => $affiliate->getKey(),
                'total_amount' => $total,
                'bank_name' => $affiliate->bank_name,
                'bank_account_name' => $affiliate->bank_account_name,
                'bank_account_number' => $affiliate->bank_account_number,
            ]);

            foreach ($commissions as $commission) {
                $commission->update(['payout_id' => $payout->getKey()]);
                $this->auditLog->handle($actor, $commission, 'commission.payout_attached', ['payout_id' => null], ['payout_id' => $payout->getKey()]);
            }

            $this->auditLog->handle($actor, $payout, 'payout.created', [], [
                'status' => PayoutStatus::Processing->value,
                'total_amount' => $total,
                'commission_ids' => $ids,
            ]);

            return $payout;
        }, 3);
    }
}
