<?php

namespace App\Filament\Resources\Umkm;

use App\Filament\Resources\Umkm\Pages\ListShops;
use App\Filament\Resources\Umkm\Pages\ViewShop;
use App\Filament\Resources\Umkm\Schemas\ShopInfolist;
use App\Filament\Resources\Umkm\Tables\ShopsTable;
use App\Models\Shop;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Antrean verifikasi & moderasi toko UMKM untuk petugas desa (PEMBANGUNAN.md 4.4 & 4.7).
 * Pelaku mendaftar lewat aplikasi; di sini petugas hanya meninjau (setujui/tolak/nonaktifkan),
 * bukan mengedit konten penjual. Multi-tenant: query dibatasi ke desa actor (admin lintas desa).
 */
class ShopResource extends Resource
{
    protected static ?string $model = Shop::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Toko UMKM';

    protected static string|\UnitEnum|null $navigationGroup = 'UMKM';

    protected static ?string $modelLabel = 'toko UMKM';

    public static function infolist(Schema $schema): Schema
    {
        return ShopInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ShopsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['owner:id,name', 'village:id,name', 'stats']);
        $actor = Filament::auth()->user();

        if ($actor && !$actor->isAdmin()) {
            $query->where('village_id', $actor->village_id);
        }

        return $query;
    }

    /** Toko dibuat pelaku lewat aplikasi — bukan dari panel. */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShops::route('/'),
            'view' => ViewShop::route('/{record}'),
        ];
    }
}
