<?php

namespace App\Filament\Resources\Pbb;

use App\Filament\Resources\Pbb\Pages\ListPbbDepositItems;
use App\Filament\Resources\Pbb\Schemas\PbbDepositItemInfolist;
use App\Filament\Resources\Pbb\Tables\PbbDepositItemsTable;
use App\Models\PbbDepositItem;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Item setoran (satu NOP+tahun per baris) — tempat bendahara menilai/ACC sebagian
 * (PEMBANGUNAN.md 4.3). Menyetujui item mengubah tagihan jadi 'lunas' (internal);
 * menolak mengembalikan ke 'belum_bayar'. Lewat PbbDepositService (transisi + audit terjaga).
 */
class PbbDepositItemResource extends Resource
{
    protected static ?string $model = PbbDepositItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $recordTitleAttribute = 'id';

    protected static ?string $navigationLabel = 'Item Setoran';

    protected static string|\UnitEnum|null $navigationGroup = 'PBB';

    protected static ?string $modelLabel = 'item setoran';

    protected static ?int $navigationSort = 31;

    public static function table(Table $table): Table
    {
        return PbbDepositItemsTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PbbDepositItemInfolist::configure($schema);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['batch.collector:id,name', 'bill.object', 'reviewer:id,name']);
        $actor = Filament::auth()->user();

        if ($actor && !$actor->isAdmin()) {
            $query->whereHas('batch', fn (Builder $b) => $b->where('village_id', $actor->village_id));
        }

        return $query;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPbbDepositItems::route('/'),
            'view' => \App\Filament\Resources\Pbb\Pages\ViewPbbDepositItem::route('/{record}'),
        ];
    }
}
