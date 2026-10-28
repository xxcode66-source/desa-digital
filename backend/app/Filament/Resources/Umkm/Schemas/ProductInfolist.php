<?php

namespace App\Filament\Resources\Umkm\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Detail produk UMKM untuk petugas (PEMBANGUNAN.md 4.4). Foto disimpan di disk privat —
 * panel hanya menampilkan nama berkas, bukan gambarnya.
 */
class ProductInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Produk')
                    ->schema([
                        TextEntry::make('name')->label('Nama Produk'),
                        TextEntry::make('shop.name')->label('Toko')->placeholder('—'),
                        TextEntry::make('village.name')->label('Desa'),
                        TextEntry::make('price')
                            ->label('Harga')
                            ->formatStateUsing(fn ($state): string => 'Rp ' . number_format((float) $state, 0, ',', '.')),
                        TextEntry::make('is_available')
                            ->label('Tersedia')
                            ->formatStateUsing(fn ($state): string => $state ? 'Ya' : 'Habis'),
                        TextEntry::make('created_at')->label('Ditambahkan')->dateTime('d M Y H:i'),
                        TextEntry::make('description')->label('Deskripsi')->placeholder('—')->columnSpanFull(),
                    ])->columns(3),
                Section::make('Foto Produk')
                    ->schema([
                        RepeatableEntry::make('photos')
                            ->hiddenLabel()
                            ->schema([
                                TextEntry::make('original_name')->label('Berkas'),
                                TextEntry::make('size')->label('Ukuran (B)')->placeholder('—'),
                                TextEntry::make('created_at')->label('Diunggah')->dateTime('d M Y H:i'),
                            ])
                            ->columns(3),
                    ]),
                Section::make('Laporan Masuk')
                    ->schema([
                        RepeatableEntry::make('reports')
                            ->hiddenLabel()
                            ->schema([
                                TextEntry::make('reason')->label('Alasan'),
                                TextEntry::make('reporter.name')->label('Pelapor')->placeholder('—'),
                                TextEntry::make('created_at')->label('Waktu')->dateTime('d M Y H:i'),
                            ])
                            ->columns(3),
                    ]),
            ]);
    }
}
