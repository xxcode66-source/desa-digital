<?php

namespace App\Filament\Resources\Umkm\Schemas;

use App\Models\Shop;
use App\Support\UmkmStatus;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Detail toko UMKM untuk petugas (PEMBANGUNAN.md 4.4): profil toko, hasil verifikasi,
 * statistik penjual, dan katalog produk. Foto/berkas disimpan di disk privat — panel
 * hanya menampilkan nama berkas.
 */
class ShopInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Toko')
                    ->schema([
                        TextEntry::make('name')->label('Nama Toko'),
                        TextEntry::make('owner.name')->label('Pemilik')->placeholder('—'),
                        TextEntry::make('village.name')->label('Desa'),
                        TextEntry::make('category')
                            ->label('Kategori')
                            ->formatStateUsing(fn (?string $state): string => config('desa.umkm.categories')[$state] ?? (string) $state),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                UmkmStatus::SHOP_ACTIVE => 'success',
                                UmkmStatus::SHOP_REJECTED => 'danger',
                                UmkmStatus::SHOP_SUSPENDED => 'warning',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn (string $state): string => UmkmStatus::label($state)),
                        TextEntry::make('is_hidden')
                            ->label('Disembunyikan')
                            ->formatStateUsing(fn ($state): string => $state ? 'Ya' : 'Tidak'),
                        TextEntry::make('rt_rw')->label('RT/RW')->placeholder('—')
                            ->state(fn (Shop $record): ?string => trim((string) $record->rt . '/' . (string) $record->rw, '/')),
                        TextEntry::make('opening_hours')->label('Jam Buka')->placeholder('—'),
                        TextEntry::make('wa_number')->label('Nomor WA')->placeholder('—'),
                        TextEntry::make('address')->label('Alamat')->placeholder('—')->columnSpanFull(),
                        TextEntry::make('description')->label('Deskripsi')->placeholder('—')->columnSpanFull(),
                    ])->columns(3),
                Section::make('Verifikasi & Statistik')
                    ->schema([
                        TextEntry::make('reviewer.name')->label('Ditinjau Oleh')->placeholder('—'),
                        TextEntry::make('reviewed_at')->label('Waktu Review')->dateTime('d M Y H:i')->placeholder('—'),
                        TextEntry::make('review_note')->label('Catatan Review')->placeholder('—')->columnSpanFull(),
                        TextEntry::make('stats.views_count')->label('Dilihat')->placeholder('0'),
                        TextEntry::make('stats.wa_clicks_count')->label('Klik WhatsApp')->placeholder('0'),
                    ])->columns(2),
                Section::make('Katalog Produk')
                    ->schema([
                        RepeatableEntry::make('products')
                            ->hiddenLabel()
                            ->schema([
                                TextEntry::make('name')->label('Produk'),
                                TextEntry::make('price')
                                    ->label('Harga')
                                    ->formatStateUsing(fn ($state): string => 'Rp ' . number_format((float) $state, 0, ',', '.')),
                                TextEntry::make('is_available')
                                    ->label('Tersedia')
                                    ->formatStateUsing(fn ($state): string => $state ? 'Ya' : 'Habis'),
                            ])
                            ->columns(3),
                    ]),
            ]);
    }
}
