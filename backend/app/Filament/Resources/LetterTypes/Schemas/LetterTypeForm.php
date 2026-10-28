<?php

namespace App\Filament\Resources\LetterTypes\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * Editor jenis surat dinamis (PEMBANGUNAN.md 4.2): kades/admin menyusun field
 * formulir tanpa ubah kode — hasilnya disimpan ke form_schema_json.
 */
class LetterTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label('Kode (unik, mis. sk_usaha)')
                    ->required()
                    ->alphaDash()
                    ->unique(ignoreRecord: true),
                TextInput::make('name')
                    ->label('Nama surat')
                    ->required()
                    ->placeholder('Contoh: Surat Keterangan Usaha'),
                Toggle::make('needs_rt')
                    ->label('Butuh pengantar RT/RW sebelum verifikasi desa?')
                    ->default(false),
                Toggle::make('is_active')
                    ->label('Aktif (terlihat oleh warga)')
                    ->default(true),
                Repeater::make('form_schema')
                    ->statePath('form_schema_json')
                    ->label('Field formulir (muncul berurutan di aplikasi warga)')
                    ->schema([
                        TextInput::make('key')
                            ->label('Kode field (mis. nama_usaha)')
                            ->required()
                            ->alphaDash(),
                        TextInput::make('label')
                            ->label('Judul yang ditampilkan')
                            ->required(),
                        Select::make('type')
                            ->label('Tipe input')
                            ->options([
                                'text' => 'Teks satu baris',
                                'textarea' => 'Teks panjang',
                                'select' => 'Pilihan ganda',
                            ])
                            ->default('text')
                            ->required()
                            ->live(),
                        TagsInput::make('options')
                            ->label('Pilihan (tekan Enter tiap pilihan)')
                            ->visible(fn (Get $get): bool => $get('type') === 'select'),
                        Toggle::make('required')
                            ->label('Wajib diisi')
                            ->default(true),
                    ])
                    ->defaultItems(1)
                    ->minItems(1)
                    ->reorderable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['label'] ?? null),
                TagsInput::make('required_attachments_json')
                    ->label('Lampiran wajib (ketik nama, tekan Enter)')
                    ->placeholder('mis. foto_ktp')
                    ->default(['foto_ktp']),
            ])
            ->statePath('data');
    }
}
