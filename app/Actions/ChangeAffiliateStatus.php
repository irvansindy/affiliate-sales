<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\AffiliateStatus;
use App\Models\Affiliate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ChangeAffiliateStatus
{
    public function __construct(private WriteAuditLog $auditLog) {}

    public function handle(User $actor, Affiliate $affiliate, AffiliateStatus $status): Affiliate
    {
        return DB::transaction(function () use ($actor, $affiliate, $status): Affiliate {
            $record = Affiliate::query()->lockForUpdate()->findOrFail($affiliate->getKey());
            $ability = match ($status) {
                AffiliateStatus::Active => 'approve',
                AffiliateStatus::Rejected => 'reject',
                AffiliateStatus::Suspended => 'suspend',
                AffiliateStatus::Pending => 'update',
            };
            Gate::forUser($actor)->authorize($ability, $record);

            if ($record->status === $status) {
                return $record;
            }

            $allowed = match ($record->status) {
                AffiliateStatus::Pending => [AffiliateStatus::Active, AffiliateStatus::Rejected],
                AffiliateStatus::Active => [AffiliateStatus::Suspended],
                AffiliateStatus::Suspended, AffiliateStatus::Rejected => [AffiliateStatus::Active],
            };

            if (! in_array($status, $allowed, true)) {
                throw ValidationException::withMessages(['status' => __('affiliates.invalid_transition')]);
            }

            $old = [
                'status' => $record->status->value,
                'approved_at' => $record->approved_at?->toIso8601String(),
            ];
            $record->status = $status;
            if ($status === AffiliateStatus::Active) {
                $record->approved_at = now();
            }
            $record->save();

            $this->auditLog->handle($actor, $record, 'affiliate.status_changed', $old, [
                'status' => $record->status->value,
                'approved_at' => $record->approved_at?->toIso8601String(),
            ]);

            return $record;
        });
    }
}
