<?php

namespace App\Filament\Resources\LetterTypes;

use App\Filament\Resources\LetterTypes\Pages\CreateLetterType;
use App\Filament\Resources\LetterTypes\Pages\EditLetterType;
use App\Filament\Resources\LetterTypes\Pages\ListLetterTypes;
use App\Filament\Resources\LetterTypes\Schemas\LetterTypeForm;
use App\Filament\Resources\LetterTypes\Tables\LetterTypesTable;
use App\Models\LetterType;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LetterTypeResource extends Resource
{
    protected static ?string $model = LetterType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Jenis Surat';

    protected static string|\UnitEnum|null $navigationGroup = 'Layanan Surat';

    protected static ?string $pluralModelLabel = 'Jenis Surat';

    public static function form(Schema $schema): Schema
    {
        return LetterTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LetterTypesTable::configure($table);
    }

    /** Admin pusat melihat semua; kades melihat jenis bawaan (NULL) + milik desanya. */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $actor = Filament::auth()->user();

        if ($actor instanceof \App\Models\User && ! $actor->isAdmin()) {
            $query->where(fn (Builder $q) => $q->whereNull('village_id')
                ->orWhere('village_id', $actor->village_id));
        }

        return $query;
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLetterTypes::route('/'),
            'create' => CreateLetterType::route('/create'),
            'edit' => EditLetterType::route('/{record}/edit'),
        ];
    }
}
