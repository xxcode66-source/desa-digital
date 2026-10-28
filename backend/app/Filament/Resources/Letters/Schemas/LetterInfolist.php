<?php

namespace App\Filament\Resources\Letters\Schemas;

use App\Support\LetterStatus;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Detail surat untuk staff: identitas pengaju, isi formulir dinamis (data_json),
 * lampiran, dan riwayat status lengkap (siapa mengubah apa, kapan).
 */
class LetterInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Pengajuan')
                    ->schema([
                        TextEntry::make('user.name')->label('Warga'),
                        TextEntry::make('user.rt')
                            ->label('RT')
                            ->placeholder('-'),
                        TextEntry::make('user.rw')
                            ->label('RW')
                            ->placeholder('-'),
                        TextEntry::make('type.name')->label('Jenis Surat'),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                LetterStatus::DONE => 'success',
                                LetterStatus::REJECTED, LetterStatus::REPAIR => 'danger',
                                default => 'warning',
                            })
                            ->formatStateUsing(fn (string $state): string => LetterStatus::label($state)),
                        TextEntry::make('number')->label('Nomor Surat')->placeholder('Belum bernomor'),
                        TextEntry::make('submitted_at')->label('Diajukan')->dateTime('d M Y H:i'),
                        TextEntry::make('note')->label('Catatan Terakhir')->placeholder('—')->columnSpanFull(),
                    ])->columns(3),
                Section::make('Isi Formulir')
                    ->schema([
                        KeyValueEntry::make('data_json')->label('')->keyLabel('Bagian')->valueLabel('Isi'),
                    ]),
                Section::make('Lampiran')
                    ->schema([
                        RepeatableEntry::make('attachments')
                            ->hiddenLabel()
                            ->schema([
                                TextEntry::make('original_name')->label('Berkas'),
                                TextEntry::make('kind')->label('Jenis'),
                                TextEntry::make('created_at')->label('Diunggah')->dateTime('d M Y H:i'),
                            ])
                            ->columns(3),
                    ]),
                Section::make('Riwayat Status')
                    ->schema([
                        RepeatableEntry::make('statusLogs')
                            ->hiddenLabel()
                            ->schema([
                                TextEntry::make('to_status')
                                    ->label('Status')
                                    ->formatStateUsing(fn (string $state): string => LetterStatus::label($state)),
                                TextEntry::make('note')->label('Catatan')->placeholder('—'),
                                TextEntry::make('actor.name')->label('Oleh')->placeholder('Sistem'),
                                TextEntry::make('created_at')->label('Waktu')->dateTime('d M Y H:i'),
                            ])
                            ->columns(4),
                    ]),
            ]);
    }
}
