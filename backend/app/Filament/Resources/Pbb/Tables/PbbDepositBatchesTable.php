<?php

namespace App\Filament\Resources\Pbb\Tables;

use App\Support\PbbStatus;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Daftar slip setoran utk bendahara/admin (PEMBANGUNAN.md 4.3). Penilaian per tagihan
 * (ACC sebagian) dilakukan di resource "Item Setoran" — batch hanya ringkasan & pantauan.
 */
class PbbDepositBatchesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('collector.name')->label('Kolektor')->searchable(),
                TextColumn::make('title')->label('Slip')->searchable()->limit(30),
                TextColumn::make('item_count')->label('# Tagihan')->sortable(),
                TextColumn::make('total_amount')
                    ->label('Total Setor')
                    ->sortable()
                    ->formatStateUsing(fn ($state): string => 'Rp ' . number_format((float) $state, 0, ',', '.')),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable()
                    ->color(fn (string $state): string => match ($state) {
                        PbbStatus::DEPOSIT_APPROVED => 'success',
                        PbbStatus::DEPOSIT_PARTIAL => 'primary',
                        PbbStatus::DEPOSIT_REJECTED => 'danger',
                        default => 'warning',
                    })
                    ->formatStateUsing(fn (string $state): string => PbbStatus::label($state)),
                TextColumn::make('created_at')->label('Disetor')->dateTime('d M Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(collect(PbbStatus::DEPOSIT_ALL)
                        ->mapWithKeys(fn (string $s): array => [$s => PbbStatus::label($s)])
                        ->all()),
            ])
            ->emptyStateIcon('heroicon-o-banknotes')
            ->emptyStateHeading('Belum ada setoran');
    }
}
