<?php

namespace App\Filament\Resources\Pbb;

use App\Filament\Resources\Pbb\Pages\ListPbbClaims;
use App\Filament\Resources\Pbb\Pages\ViewPbbClaim;
use App\Filament\Resources\Pbb\Schemas\PbbClaimInfolist;
use App\Filament\Resources\Pbb\Tables\PbbClaimsTable;
use App\Models\PbbClaim;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Antrean klaim kepemilikan NOP (PEMBANGUNAN.md 4.3). Warga mengajukan lewat aplikasi;
 * operator/kades/admin menyetujui atau menolak di sini. Multi-tenant: dibatasi desa actor.
 */
class PbbClaimResource extends Resource
{
    protected static ?string $model = PbbClaim::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $recordTitleAttribute = 'id';

    protected static ?string $navigationLabel = 'Klaim NOP';

    protected static string|\UnitEnum|null $navigationGroup = 'PBB';

    protected static ?string $modelLabel = 'klaim kepemilikan';

    protected static ?int $navigationSort = 20;

    public static function infolist(Schema $schema): Schema
    {
        return PbbClaimInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PbbClaimsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['user:id,name', 'object', 'village:id,name']);
        $actor = Filament::auth()->user();

        if ($actor && !$actor->isAdmin()) {
            $query->where('village_id', $actor->village_id);
        }

        return $query;
    }

    /** Klaim diajukan warga lewat aplikasi — bukan dari panel. */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPbbClaims::route('/'),
            'view' => ViewPbbClaim::route('/{record}'),
        ];
    }
}
