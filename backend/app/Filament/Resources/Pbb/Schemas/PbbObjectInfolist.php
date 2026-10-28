<?php

namespace App\Filament\Resources\Pbb\Schemas;

use App\Support\PbbStatus;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Detail objek pajak utk petugas: identitas WP, luas, dan daftar tagihan per tahun beserta
 * status. Nama WP ditampilkan penuh di panel (hanya disamarkan di cek NOP publik).
 */
class PbbObjectInfolist
{
    /** Format rupiah pendek: 150000 -> "Rp 150.000". */
    private static function rupiah(): callable
    {
        return fn ($state): string => 'Rp ' . number_format((float) $state, 0, ',', '.');
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Objek Pajak')
                ->schema([
                    TextEntry::make('nop')->label('NOP')->copyable(),
                    TextEntry::make('wp_name')->label('Nama WP'),
                    TextEntry::make('village.name')->label('Desa'),
                    TextEntry::make('rt')->label('RT/RW')->formatStateUsing(
                        fn (?string $state, $record): string => ($state ?? '-').'/'.($record->rw ?? '-')
                    ),
                    TextEntry::make('land_area')->label('Luas Tanah (m²)')->placeholder('—')->formatStateUsing(fn ($state): string => number_format((float) $state, 2, ',', '.') . ' m²'),
                    TextEntry::make('building_area')->label('Luas Bangunan (m²)')->placeholder('—')->formatStateUsing(fn ($state): string => number_format((float) $state, 2, ',', '.') . ' m²'),
                    TextEntry::make('address')->label('Alamat')->columnSpanFull()->placeholder('—'),
                ])->columns(3),
            Section::make('Tagihan per Tahun')
                ->schema([
                    RepeatableEntry::make('bills')
                        ->hiddenLabel()
                        ->schema([
                            TextEntry::make('year')->label('Tahun'),
                            TextEntry::make('amount')->label('Pokok')->formatStateUsing(self::rupiah()),
                            TextEntry::make('penalty')->label('Denda')->formatStateUsing(self::rupiah()),
                            TextEntry::make('status')
                                ->label('Status')
                                ->badge()
                                ->color(fn (string $state): string => match ($state) {
                                    PbbStatus::BILL_PAID => 'success',
                                    PbbStatus::BILL_PENDING => 'warning',
                                    default => 'gray',
                                })
                                ->formatStateUsing(fn (string $state): string => PbbStatus::label($state)),
                        ])
                        ->columns(4),
                ]),
        ]);
    }
}
