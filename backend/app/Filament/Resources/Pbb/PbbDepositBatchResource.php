<?php

namespace App\Filament\Resources\Pbb;

use App\Filament\Resources\Pbb\Pages\ListPbbDepositBatches;
use App\Filament\Resources\Pbb\Tables\PbbDepositBatchesTable;
use App\Models\PbbDepositBatch;
use App\Support\PbbStatus;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Slip setoran PBB dari RT/Kolektor (PEMBANGUNAN.md 4.3). Bendahara menilai per tagihan
 * lewat resource "Item Setoran" (ACC sebagian). Batch hanya daftar & pantau di sini.
 */
class PbbDepositBatchResource extends Resource
{
    protected static ?string $model = PbbDepositBatch::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $navigationLabel = 'Setoran PBB';

    protected static string|\UnitEnum|null $navigationGroup = 'PBB';

    protected static ?string $modelLabel = 'setoran';

    protected static ?int $navigationSort = 30;

    public static function table(Table $table): Table
    {
        return PbbDepositBatchesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['collector:id,name', 'village:id,name']);
        $actor = Filament::auth()->user();

        if ($actor && !$actor->isAdmin()) {
            $query->where('village_id', $actor->village_id);
        }

        return $query;
    }

    public static function getNavigationBadge(): ?string
    {
        return static::canViewAny()
            ? (string) static::getEloquentQuery()->where('status', PbbStatus::DEPOSIT_PENDING)->count()
            : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    /** Setoran dibuat kolektor lewat aplikasi — bukan dari panel. */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPbbDepositBatches::route('/'),
        ];
    }
}
