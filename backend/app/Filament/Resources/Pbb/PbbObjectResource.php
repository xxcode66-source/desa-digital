<?php

namespace App\Filament\Resources\Pbb;

use App\Filament\Resources\Pbb\Pages\ListPbbObjects;
use App\Filament\Resources\Pbb\Pages\ViewPbbObject;
use App\Filament\Resources\Pbb\Schemas\PbbObjectInfolist;
use App\Filament\Resources\Pbb\Tables\PbbObjectsTable;
use App\Models\PbbObject;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Database objek pajak PBB utk petugas desa (PEMBANGUNAN.md 4.3 & 4.7). Operator/admin mengimpor
 * CSV sini; warga mengajukan klaim & melihat NOP-nya lewat aplikasi. Multi-tenant: query dibatasi
 * ke desa actor (kecuali admin lintas desa).
 */
class PbbObjectResource extends Resource
{
    protected static ?string $model = PbbObject::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static ?string $recordTitleAttribute = 'nop';

    protected static ?string $navigationLabel = 'Objek PBB';

    protected static string|\UnitEnum|null $navigationGroup = 'PBB';

    protected static ?string $modelLabel = 'objek pajak';

    protected static ?int $navigationSort = 10;

    public static function infolist(Schema $schema): Schema
    {
        return PbbObjectInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PbbObjectsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->withCount('bills');
        $actor = Filament::auth()->user();

        if ($actor && !$actor->isAdmin()) {
            $query->where('village_id', $actor->village_id);
        }

        return $query;
    }

    /** Data masuk lewat import CSV / klaim warga — bukan dibuat manual di panel. */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPbbObjects::route('/'),
            'view' => ViewPbbObject::route('/{record}'),
        ];
    }
}
