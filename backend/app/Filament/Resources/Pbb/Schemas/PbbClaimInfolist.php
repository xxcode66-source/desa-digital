<?php

namespace App\Filament\Resources\Pbb\Schemas;

use App\Support\PbbStatus;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Detail klaim NOP utk peninjau: pemohon, objek yang diklaim, catatan warga, berkas bukti,
 * dan hasil review. Bukti disimpan di disk privat — panel menampilkan nama berkas saja.
 */
class PbbClaimInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Klaim')
                ->schema([
                    TextEntry::make('user.name')->label('Pemohon'),
                    TextEntry::make('object.nop')->label('NOP')->copyable(),
                    TextEntry::make('object.wp_name')->label('Nama WP Tercatat')->placeholder('—'),
                    TextEntry::make('object.address')->label('Alamat Objek')->placeholder('—'),
                    TextEntry::make('status')
                        ->label('Status')
                        ->badge()
                        ->color(fn (string $state): string => match ($state) {
                            PbbStatus::CLAIM_APPROVED => 'success',
                            PbbStatus::CLAIM_REJECTED => 'danger',
                            default => 'warning',
                        })
                        ->formatStateUsing(fn (string $state): string => PbbStatus::label($state)),
                    TextEntry::make('created_at')->label('Diajukan')->dateTime('d M Y H:i'),
                    TextEntry::make('note')->label('Catatan Warga')->columnSpanFull()->placeholder('—'),
                ])->columns(3),
            Section::make('Bukti & Review')
                ->schema([
                    TextEntry::make('evidence_name')->label('Berkas Bukti')->placeholder('—'),
                    TextEntry::make('reviewer.name')->label('Dinilai Oleh')->placeholder('—'),
                    TextEntry::make('reviewed_at')->label('Waktu Review')->dateTime('d M Y H:i')->placeholder('—'),
                    TextEntry::make('review_note')->label('Catatan Review')->columnSpanFull()->placeholder('—'),
                ])->columns(2),
        ]);
    }
}
