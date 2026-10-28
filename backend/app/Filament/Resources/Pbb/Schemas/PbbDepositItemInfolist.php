<?php

namespace App\Filament\Resources\Pbb\Schemas;

use App\Support\PbbStatus;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Detail satu item setoran: tagihan NOP yang disetor, nominal, bukti, & hasil penilaian
 * bendahara. Dipakai saat meninjau sebelum ACC sebagian (PEMBANGUNAN.md 4.3).
 */
class PbbDepositItemInfolist
{
    private static function rupiah(): callable
    {
        return fn ($state): string => 'Rp ' . number_format((float) $state, 0, ',', '.');
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Tagihan')
                ->schema([
                    TextEntry::make('bill.object.nop')->label('NOP')->copyable(),
                    TextEntry::make('bill.object.wp_name')->label('Nama WP')->placeholder('—'),
                    TextEntry::make('bill.year')->label('Tahun'),
                    TextEntry::make('bill.status')
                        ->label('Status Tagihan')
                        ->badge()
                        ->color(fn (string $state): string => match ($state) {
                            PbbStatus::BILL_PAID => 'success',
                            PbbStatus::BILL_PENDING => 'warning',
                            default => 'gray',
                        })
                        ->formatStateUsing(fn (string $state): string => PbbStatus::label($state)),
                    TextEntry::make('bill_total')->label('Semestakan')
                        ->getStateUsing(fn ($record): float => (float) $record->bill?->total())
                        ->formatStateUsing(self::rupiah()),
                    TextEntry::make('amount_claimed')->label('Disetor')->formatStateUsing(self::rupiah()),
                ])->columns(3),
            Section::make('Setoran & Penilaian')
                ->schema([
                    TextEntry::make('batch.title')->label('Slip Setoran'),
                    TextEntry::make('batch.collector.name')->label('Kolektor'),
                    TextEntry::make('status')
                        ->label('Status Item')
                        ->badge()
                        ->color(fn (string $state): string => match ($state) {
                            PbbStatus::ITEM_APPROVED => 'success',
                            PbbStatus::ITEM_REJECTED => 'danger',
                            default => 'warning',
                        })
                        ->formatStateUsing(fn (string $state): string => PbbStatus::label($state)),
                    TextEntry::make('evidence_name')->label('Berkas Bukti')->placeholder('—'),
                    TextEntry::make('reviewer.name')->label('Dinilai Oleh')->placeholder('—'),
                    TextEntry::make('reviewed_at')->label('Waktu')->dateTime('d M Y H:i')->placeholder('—'),
                    TextEntry::make('review_note')->label('Catatan Penilaian')->columnSpanFull()->placeholder('—'),
                ])->columns(3),
        ]);
    }
}
