<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Affiliates\Schemas;

use App\Enums\AffiliateStatus;
use App\Models\Affiliate;
use App\Models\Product;
use App\Services\DisplayFormatter;
use App\Services\ReferralLinkBuilder;
use Filament\Facades\Filament;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AffiliateInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(['default' => 1, 'md' => 2])->components([
            TextEntry::make('user.name')->label(__('affiliates.name')),
            TextEntry::make('user.email')->label(__('affiliates.email')),
            TextEntry::make('status')->label(__('affiliates.status'))->badge()
                ->formatStateUsing(fn (AffiliateStatus $state): string => __('affiliates.statuses.'.$state->value))
                ->color(fn (AffiliateStatus $state): string => match ($state) {
                    AffiliateStatus::Pending => 'warning',
                    AffiliateStatus::Active => 'success',
                    AffiliateStatus::Suspended, AffiliateStatus::Rejected => 'danger',
                }),
            TextEntry::make('phone')->label(__('affiliates.phone'))->placeholder('—'),
            TextEntry::make('referral_code')->label(__('affiliates.referral_code')),
            TextEntry::make('approved_at')->label(__('affiliates.approved_at'))
                ->formatStateUsing(fn ($state): string => DisplayFormatter::dateTime($state))->placeholder('—'),
            TextEntry::make('bank_name')->label(__('affiliates.bank_name'))->placeholder('—'),
            TextEntry::make('bank_account_name')->label(__('affiliates.bank_account_name'))->placeholder('—'),
            TextEntry::make('masked_account')->label(__('affiliates.bank_account_number'))
                ->state(fn (Affiliate $record): string => DisplayFormatter::bankAccount($record->bank_account_number)),
            Section::make(__('referrals.navigation'))
                ->description(__('referrals.admin_description'))
                ->schema([
                    RepeatableEntry::make('referral_links')
                        ->hiddenLabel()
                        ->state(fn (Affiliate $record): array => Product::query()
                            ->where('is_active', true)
                            ->orderBy('name')
                            ->get()
                            ->map(fn (Product $product): array => [
                                'name' => $product->name,
                                'url' => app(ReferralLinkBuilder::class)->for(Filament::auth()->user(), $record, $product),
                            ])->all())
                        ->placeholder(__('referrals.empty_heading'))
                        ->schema([
                            TextEntry::make('name')->label(__('referrals.product')),
                            TextEntry::make('url')->label(__('referrals.link'))
                                ->placeholder(fn (Affiliate $record): string => $record->isActive()
                                    ? __('referrals.not_ready')
                                    : __('referrals.affiliate_inactive'))
                                ->copyable(fn (?string $state): bool => filled($state))
                                ->copyMessage(__('referrals.copied'))
                                ->wrap(),
                        ])
                        ->columns(['default' => 1, 'md' => 2]),
                ])->columnSpanFull(),
        ]);
    }
}
