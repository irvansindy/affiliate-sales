<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Payouts\Pages;

use App\Actions\CreatePayout as CreatePayoutAction;
use App\Filament\Admin\Resources\Payouts\PayoutResource;
use App\Models\Affiliate;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePayout extends CreateRecord
{
    protected static string $resource = PayoutResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        return app(CreatePayoutAction::class)->handle(
            Filament::auth()->user(),
            Affiliate::query()->findOrFail($data['affiliate_id']),
            array_map('intval', $data['commissions']),
        );
    }
}
