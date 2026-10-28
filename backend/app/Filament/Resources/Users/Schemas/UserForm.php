<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->label('Nama lengkap')->required(),
                TextInput::make('phone')->label('Nomor HP')->required(),
                TextInput::make('nik')->label('NIK')->numeric()->length(16),
                // Status diubah lewat aksi Setujui/Tolak (ada audit log), bukan edit bebas.
                Select::make('status')
                    ->label('Status verifikasi')
                    ->options([
                        User::STATUS_PENDING => 'Menunggu',
                        User::STATUS_VERIFIED => 'Terverifikasi',
                        User::STATUS_REJECTED => 'Ditolak',
                    ])
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('rt')->label('RT')->maxLength(5),
                TextInput::make('rw')->label('RW')->maxLength(5),
                // Keanggotaan role hanya untuk kades/admin (UserPolicy::manageRoles).
                Select::make('roles')
                    ->label('Role')
                    ->multiple()
                    ->relationship('roles', 'label')
                    ->preload()
                    ->visible(fn (?User $record): bool => auth()->user()?->can('manageRoles', $record ?? new User())),
            ]);
    }
}
