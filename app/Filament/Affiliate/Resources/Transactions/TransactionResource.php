<?php

declare(strict_types=1);

namespace App\Filament\Affiliate\Resources\Transactions;

use App\Filament\Admin\Resources\Transactions\Schemas\TransactionInfolist;
use App\Filament\Affiliate\Resources\Transactions\Pages\ListTransactions;
use App\Filament\Affiliate\Resources\Transactions\Pages\ViewTransaction;
use App\Filament\Affiliate\Resources\Transactions\Tables\TransactionsTable;
use App\Models\Transaction;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    public static function getModelLabel(): string
    {
        return __('transactions.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('transactions.plural_label');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('affiliate_id', Filament::auth()->user()->affiliate->getKey())
            ->with(['product', 'affiliate.user']);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TransactionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TransactionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTransactions::route('/'),
            'view' => ViewTransaction::route('/{record}'),
        ];
    }
}
