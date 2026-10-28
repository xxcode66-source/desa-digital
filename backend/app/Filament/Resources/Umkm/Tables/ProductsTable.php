<?php

namespace App\Filament\Resources\Umkm\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * Katalog produk UMKM untuk peninjauan petugas (PEMBANGUNAN.md 4.4). Produk dibuat & dikelola
 * penjual lewat aplikasi; panel hanya membaca. Moderasi (sembunyikan) dilakukan lewat laporan
 * dengan membekukan tokonya, bukan menghapus produk.
 */
class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Produk')->searchable()->limit(40),
                TextColumn::make('shop.name')->label('Toko')->searchable()->placeholder('—'),
                TextColumn::make('price')
                    ->label('Harga')
                    ->sortable()
                    ->formatStateUsing(fn ($state): string => 'Rp ' . number_format((float) $state, 0, ',', '.')),
                TextColumn::make('is_available')
                    ->label('Status')
                    ->badge()
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray')
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Tersedia' : 'Habis'),
                TextColumn::make('photos_count')->label('Foto')->counts('photos')->color('gray'),
                TextColumn::make('created_at')->label('Ditambahkan')->dateTime('d M Y')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TernaryFilter::make('is_available')->label('Ketersediaan')->placeholder('Semua')->trueLabel('Tersedia')->falseLabel('Habis'),
            ])
            ->recordActions([
                ViewAction::make()->authorize('view'),
            ])
            ->emptyStateIcon('heroicon-o-shopping-bag')
            ->emptyStateHeading('Belum ada produk');
    }
}
