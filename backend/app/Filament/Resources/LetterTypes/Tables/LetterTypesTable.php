<?php

namespace App\Filament\Resources\LetterTypes\Tables;

use App\Models\LetterType;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LetterTypesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama surat')->searchable()->sortable(),
                TextColumn::make('code')->label('Kode')->badge(),
                TextColumn::make('form_schema_json')
                    ->label('Field')
                    ->formatStateUsing(fn (?array $state): string => $state
                        ? implode(', ', array_column($state, 'label'))
                        : '-')
                    ->wrap()
                    ->limit(60),
                IconColumn::make('needs_rt')->label('RT?')->boolean(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
                TextColumn::make('letters_count')->counts('letters')->label('Surat')->sortable(),
                TextColumn::make('village.name')
                    ->label('Desa')
                    ->placeholder('Bawaan (semua desa)')
                    ->visible(fn (): bool => (bool) auth()->user()?->isAdmin()),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make()->authorize('update'),
                DeleteAction::make()
                    ->authorize('delete')
                    ->hidden(fn (LetterType $record): bool => $record->letters()->exists()),
            ])
            ->emptyStateIcon('heroicon-o-rectangle-stack')
            ->emptyStateHeading('Belum ada jenis surat');
    }
}
