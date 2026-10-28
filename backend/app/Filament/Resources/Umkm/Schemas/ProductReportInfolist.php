<?php

namespace App\Filament\Resources\Umkm\Schemas;

use App\Support\UmkmStatus;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Detail laporan moderasi UMKM untuk petugas (PEMBANGUNAN.md 4.4).
 */
class ProductReportInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Laporan')
                    ->schema([
                        TextEntry::make('reporter.name')->label('Pelapor')->placeholder('—'),
                        TextEntry::make('village.name')->label('Desa'),
                        TextEntry::make('product.name')->label('Produk')->placeholder('—'),
                        TextEntry::make('shop.name')->label('Toko')->placeholder('—'),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                UmkmStatus::REPORT_RESOLVED => 'success',
                                UmkmStatus::REPORT_DISMISSED => 'gray',
                                default => 'danger',
                            })
                            ->formatStateUsing(fn (string $state): string => UmkmStatus::label($state)),
                        TextEntry::make('created_at')->label('Masuk')->dateTime('d M Y H:i'),
                        TextEntry::make('reason')->label('Alasan')->columnSpanFull(),
                    ])->columns(2),
                Section::make('Penanganan')
                    ->schema([
                        TextEntry::make('handler.name')->label('Ditangani Oleh')->placeholder('—'),
                        TextEntry::make('handled_at')->label('Waktu')->dateTime('d M Y H:i')->placeholder('—'),
                        TextEntry::make('handle_note')->label('Catatan')->placeholder('—')->columnSpanFull(),
                    ])->columns(2),
            ]);
    }
}
