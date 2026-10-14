<?php

declare(strict_types=1);

namespace App\Filament\Affiliate\Pages;

use App\Actions\UpdateAffiliateProfile;
use App\Services\DisplayFormatter;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class Profile extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected string $view = 'filament.affiliate.pages.profile';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('affiliate_panel.profile');
    }

    public function getTitle(): string
    {
        return __('affiliate_panel.profile');
    }

    public function mount(): void
    {
        $user = Filament::auth()->user();
        $affiliate = $user->affiliate;

        $this->form->fill([
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $affiliate->phone,
            'bank_name' => $affiliate->bank_name,
            'bank_account_name' => $affiliate->bank_account_name,
            'bank_account_number' => null,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->columns(['default' => 1, 'md' => 2])->components([
            TextInput::make('name')->label(__('affiliate_panel.name'))->required()->maxLength(255),
            TextInput::make('email')->label(__('affiliate_panel.email'))->disabled()->dehydrated(false),
            TextInput::make('phone')->label(__('affiliate_panel.phone'))->tel()->maxLength(30),
            TextInput::make('bank_name')->label(__('payouts.bank'))->maxLength(100),
            TextInput::make('bank_account_name')->label(__('payouts.account_name'))->maxLength(150),
            TextInput::make('bank_account_number')->label(__('payouts.account_number'))
                ->maxLength(30)->regex('/^[0-9]{6,30}$/')
                ->helperText(fn (): string => __('affiliate_panel.bank_hint', [
                    'masked' => filled(Filament::auth()->user()->affiliate->bank_account_number)
                        ? DisplayFormatter::bankAccount(Filament::auth()->user()->affiliate->bank_account_number)
                        : __('affiliate_panel.not_set'),
                ])),
        ]);
    }

    public function save(): void
    {
        app(UpdateAffiliateProfile::class)->handle(Filament::auth()->user(), $this->form->getState());
        $this->data['bank_account_number'] = null;
        Notification::make()->title(__('affiliate_panel.saved'))->success()->duration(4000)->send();
    }
}
