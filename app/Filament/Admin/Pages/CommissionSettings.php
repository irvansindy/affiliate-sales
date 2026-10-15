<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Actions\UpdateCommissionRate;
use App\Models\Setting;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class CommissionSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected string $view = 'filament.admin.pages.commission-settings';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('sales_submissions.rate_settings');
    }

    public function getTitle(): string
    {
        return __('sales_submissions.rate_settings');
    }

    public function mount(): void
    {
        $this->form->fill(['rate' => Setting::get('default_commission_rate', '10')]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            TextInput::make('rate')->label(__('sales_submissions.rate'))
                ->helperText(__('sales_submissions.rate_help'))->required()
                ->regex('/^(?:100(?:\.0{1,2})?|[0-9]{1,2}(?:\.[0-9]{1,2})?)$/'),
        ]);
    }

    public function save(): void
    {
        app(UpdateCommissionRate::class)->handle(Filament::auth()->user(), (string) $this->form->getState()['rate']);
        Notification::make()->title(__('sales_submissions.rate_saved'))->success()->send();
    }
}
