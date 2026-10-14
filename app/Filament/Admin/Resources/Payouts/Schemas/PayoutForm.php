<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Payouts\Schemas;

use App\Enums\CommissionStatus;
use App\Models\Affiliate;
use App\Models\Commission;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class PayoutForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('affiliate_id')->label(__('payouts.affiliate'))->required()->searchable()->live()
                ->options(fn (): array => Affiliate::query()->with('user')->get()->mapWithKeys(
                    fn (Affiliate $affiliate): array => [$affiliate->id => $affiliate->user->name.' ('.$affiliate->referral_code.')']
                )->all()),
            Select::make('commissions')->label(__('payouts.commissions'))->required()->multiple()->searchable()
                ->options(fn (Get $get): array => Commission::query()
                    ->where('affiliate_id', $get('affiliate_id'))
                    ->where('status', CommissionStatus::Approved)
                    ->whereNull('payout_id')
                    ->orderBy('id')->get()
                    ->mapWithKeys(fn (Commission $commission): array => [
                        $commission->id => '#'.$commission->id.' — Rp '.number_format($commission->amount, 0, ',', '.'),
                    ])->all()),
        ]);
    }
}
