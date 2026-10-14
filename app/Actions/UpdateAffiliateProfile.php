<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Affiliate;
use App\Models\User;
use App\Services\DisplayFormatter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

final class UpdateAffiliateProfile
{
    public function __construct(private readonly WriteAuditLog $auditLog) {}

    /** @param array<string, mixed> $input */
    public function handle(User $actor, array $input): Affiliate
    {
        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_account_name' => ['nullable', 'string', 'max:150'],
            'bank_account_number' => ['nullable', 'regex:/^[0-9]{6,30}$/'],
        ])->validate();

        return DB::transaction(function () use ($actor, $data): Affiliate {
            $affiliate = Affiliate::query()->where('user_id', $actor->getKey())->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('updateProfile', $affiliate);

            $old = [
                'name' => $actor->name,
                'phone' => $affiliate->phone,
                'bank_name' => $affiliate->bank_name,
                'bank_account_name' => $affiliate->bank_account_name,
                'bank_account_number' => DisplayFormatter::bankAccount($affiliate->bank_account_number),
            ];
            $oldAccountNumber = $affiliate->bank_account_number;

            $actor->update(['name' => $data['name']]);
            $affiliate->update([
                'phone' => array_key_exists('phone', $data) ? $data['phone'] : $affiliate->phone,
                'bank_name' => array_key_exists('bank_name', $data) ? $data['bank_name'] : $affiliate->bank_name,
                'bank_account_name' => array_key_exists('bank_account_name', $data) ? $data['bank_account_name'] : $affiliate->bank_account_name,
                'bank_account_number' => filled($data['bank_account_number'] ?? null)
                    ? $data['bank_account_number']
                    : $affiliate->bank_account_number,
            ]);

            $new = [
                'name' => $actor->name,
                'phone' => $affiliate->phone,
                'bank_name' => $affiliate->bank_name,
                'bank_account_name' => $affiliate->bank_account_name,
                'bank_account_number' => DisplayFormatter::bankAccount($affiliate->bank_account_number),
            ];
            if ($old !== $new || $oldAccountNumber !== $affiliate->bank_account_number) {
                if ($oldAccountNumber !== $affiliate->bank_account_number) {
                    $new['bank_account_number_changed'] = true;
                }
                $this->auditLog->handle($actor, $affiliate, 'affiliate.profile_updated', $old, $new);
            }

            $actor->setRelation('affiliate', $affiliate);

            return $affiliate;
        });
    }
}
