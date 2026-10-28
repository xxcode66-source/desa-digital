<?php

namespace App\Filament\Resources\Letters;

use App\Filament\Resources\Letters\Pages\ListLetters;
use App\Filament\Resources\Letters\Pages\ViewLetter;
use App\Filament\Resources\Letters\Schemas\LetterInfolist;
use App\Filament\Resources\Letters\Tables\LettersTable;
use App\Models\Letter;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LetterResource extends Resource
{
    protected static ?string $model = Letter::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $recordTitleAttribute = 'id';

    protected static ?string $navigationLabel = 'Antrean Surat';

    protected static string|\UnitEnum|null $navigationGroup = 'Layanan Surat';

    protected static ?string $modelLabel = 'surat';

    public static function infolist(Schema $schema): Schema
    {
        return LetterInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LettersTable::configure($table);
    }

    /** Multi-tenant: surat desa sendiri; admin lintas desa. */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['user:id,name', 'type:id,name']);
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
            'index' => ListLetters::route('/'),
            'view' => ViewLetter::route('/{record}'),
        ];
    }
}
