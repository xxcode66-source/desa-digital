<?php

namespace App\Filament\Resources\Complaints;

use App\Filament\Resources\Complaints\Pages\ListComplaints;
use App\Filament\Resources\Complaints\Pages\ViewComplaint;
use App\Filament\Resources\Complaints\Schemas\ComplaintInfolist;
use App\Filament\Resources\Complaints\Tables\ComplaintsTable;
use App\Models\Complaint;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Antrean pengaduan untuk petugas desa (PEMBANGUNAN.md 4.5 & 4.7). Warga mengajukan lewat
 * aplikasi; di sini operator/kades/admin memproses status & membalas. Multi-tenant: setiap
 * query dibatasi ke desa actor (kecuali admin lintas desa).
 */
class ComplaintResource extends Resource
{
    protected static ?string $model = Complaint::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $navigationLabel = 'Pengaduan Warga';

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaduan';

    protected static ?string $modelLabel = 'pengaduan';

    public static function infolist(Schema $schema): Schema
    {
        return ComplaintInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ComplaintsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['user:id,name', 'village:id,name']);
        $actor = Filament::auth()->user();

        if ($actor instanceof \App\Models\User && ! $actor->isAdmin()) {
            $query->where('village_id', $actor->village_id);
        }

        return $query;
    }

    /** Warga mengajukan lewat aplikasi — bukan dari panel. */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListComplaints::route('/'),
            'view' => ViewComplaint::route('/{record}'),
        ];
    }
}
