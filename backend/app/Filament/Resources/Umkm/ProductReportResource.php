<?php

namespace App\Filament\Resources\Umkm;

use App\Filament\Resources\Umkm\Pages\ListProductReports;
use App\Filament\Resources\Umkm\Pages\ViewProductReport;
use App\Filament\Resources\Umkm\Schemas\ProductReportInfolist;
use App\Filament\Resources\Umkm\Tables\ProductReportsTable;
use App\Models\ProductReport;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Antrean moderasi laporan UMKM untuk petugas desa (PEMBANGUNAN.md 4.4 & 4.7). Warga melapor
 * lewat aplikasi; di sini laporan ditindaklanjuti/diabaikan. Multi-tenant: dibatasi desa actor.
 */
class ProductReportResource extends Resource
{
    protected static ?string $model = ProductReport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static ?string $navigationLabel = 'Laporan UMKM';

    protected static string|\UnitEnum|null $navigationGroup = 'UMKM';

    protected static ?string $modelLabel = 'laporan UMKM';

    public static function infolist(Schema $schema): Schema
    {
        return ProductReportInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductReportsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['reporter:id,name', 'product:id,name', 'shop:id,name', 'village:id,name', 'handler:id,name']);
        $actor = Filament::auth()->user();

        if ($actor && !$actor->isAdmin()) {
            $query->where('village_id', $actor->village_id);
        }

        return $query;
    }

    /** Laporan diajukan warga lewat aplikasi — bukan dari panel. */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductReports::route('/'),
            'view' => ViewProductReport::route('/{record}'),
        ];
    }
}
