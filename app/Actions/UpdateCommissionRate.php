<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

final class UpdateCommissionRate
{
    public function __construct(private readonly WriteAuditLog $auditLog) {}

    public function handle(User $admin, string $rate): Setting
    {
        Gate::forUser($admin)->authorize('updateCommissionRate', Setting::class);
        $validated = Validator::make(['rate' => $rate], [
            'rate' => ['required', 'regex:/^(?:100(?:\.0{1,2})?|[0-9]{1,2}(?:\.[0-9]{1,2})?)$/'],
        ])->validate();
        [$whole, $fraction] = array_pad(explode('.', $validated['rate'], 2), 2, '');
        $normalized = $whole.'.'.str_pad($fraction, 2, '0');

        return DB::transaction(function () use ($admin, $normalized): Setting {
            $setting = Setting::query()->lockForUpdate()->firstOrCreate(
                ['key' => 'default_commission_rate'], ['value' => '10'],
            );
            $old = $setting->value;
            $setting->update(['value' => $normalized]);
            $this->auditLog->handle($admin, $setting, 'setting.commission_rate.updated',
                ['value' => $old], ['value' => $normalized]);

            return $setting;
        });
    }
}
