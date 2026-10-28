<?php

namespace App\Filament\Resources\Complaints\Schemas;

use App\Support\ComplaintStatus;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Detail pengaduan untuk petugas: identitas pelapor (selalu terlihat walau anonim — anonim
 * hanya untuk papan publik), isi laporan, foto bukti, dan kronologi (balasan + riwayat status).
 */
class ComplaintInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Pengaduan')
                    ->schema([
                        TextEntry::make('user.name')->label('Pelapor'),
                        TextEntry::make('village.name')->label('Desa'),
                        TextEntry::make('category')
                            ->label('Kategori')
                            ->formatStateUsing(fn (string $state): string => config('desa.complaints.categories')[$state] ?? $state),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                ComplaintStatus::DONE => 'success',
                                ComplaintStatus::REJECTED => 'danger',
                                ComplaintStatus::IN_PROGRESS => 'warning',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn (string $state): string => ComplaintStatus::label($state)),
                        TextEntry::make('is_anonymous')
                            ->label('Anonim (publik)')
                            ->formatStateUsing(fn ($state): string => $state ? 'Ya' : 'Tidak'),
                        TextEntry::make('submitted_at')->label('Diajukan')->dateTime('d M Y H:i'),
                        TextEntry::make('location')->label('Lokasi')->placeholder('—'),
                        TextEntry::make('title')->label('Judul')->columnSpanFull(),
                        TextEntry::make('description')->label('Isi Laporan')->columnSpanFull(),
                    ])->columns(3),
                Section::make('Foto Bukti')
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
                Section::make('Kronologi & Balasan')
                    ->schema([
                        RepeatableEntry::make('replies')
                            ->hiddenLabel()
                            ->schema([
                                TextEntry::make('author.name')
                                    ->label('Oleh')
                                    ->placeholder('Sistem')
                                    ->formatStateUsing(fn (?string $state, $record): string => ($record->is_staff ? '[Petugas] ' : '') . ($state ?? 'Sistem')),
                                TextEntry::make('body')->label('Pesan')->placeholder('—'),
                                TextEntry::make('to_status')
                                    ->label('Status')
                                    ->placeholder('—')
                                    ->formatStateUsing(fn (?string $state): string => $state ? ComplaintStatus::label($state) : '—'),
                                TextEntry::make('created_at')->label('Waktu')->dateTime('d M Y H:i'),
                            ])
                            ->columns(4),
                    ]),
            ]);
    }
}
