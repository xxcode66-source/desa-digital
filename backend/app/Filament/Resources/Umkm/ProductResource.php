<?php

namespace App\Filament\Resources\Umkm;

use App\Filament\Resources\Umkm\Pages\ListProducts;
use App\Filament\Resources\Umkm\Pages\ViewProduct;
use App\Filament\Resources\Umkm\Schemas\ProductInfolist;
use App\Filament\Resources\Umkm\Tables\ProductsTable;
use App\Models\Product;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Katalog produk UMKM untuk peninjauan petugas (PEMBANGUNAN.md 4.4 & 4.7). Produk dikelola
 * penjual lewat aplikasi; panel hanya membaca. Multi-tenant: dibatasi ke desa actor (admin bebas).
 */
class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Produk UMKM';

    protected static string|\UnitEnum|null $navigationGroup = 'UMKM';

    protected static ?string $modelLabel = 'produk UMKM';

    public static function infolist(Schema $schema): Schema
    {
        return ProductInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['shop:id,name', 'village:id,name']);
        $actor = Filament::auth()->user();

        if ($actor && !$actor->isAdmin()) {
            $query->where('village_id', $actor->village_id);
        }

        return $query;
    }

    /** Produk dibuat penjual lewat aplikasi — bukan dari panel. */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'view' => ViewProduct::route('/{record}'),
        ];
    }
}
