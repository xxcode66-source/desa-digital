<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use App\Services\AuditLogger;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('phone')->label('Nomor HP')->searchable(),
                TextColumn::make('nik')->label('NIK')->description('Hanya terlihat staff')->searchable(),
                TextColumn::make('rt')->label('RT'),
                TextColumn::make('rw')->label('RW'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable()
                    ->color(fn (string $state): string => match ($state) {
                        User::STATUS_VERIFIED => 'success',
                        User::STATUS_REJECTED => 'danger',
                        default => 'warning',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        User::STATUS_VERIFIED => 'Terverifikasi',
                        User::STATUS_REJECTED => 'Ditolak',
                        default => 'Menunggu',
                    }),
                TextColumn::make('roles.name')
                    ->label('Role')
                    ->badge()
                    ->separator(','),
                TextColumn::make('created_at')
                    ->label('Daftar')
                    ->dateTime('d M Y')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status verifikasi')
                    ->options([
                        User::STATUS_PENDING => 'Menunggu',
                        User::STATUS_VERIFIED => 'Terverifikasi',
                        User::STATUS_REJECTED => 'Ditolak',
                    ]),
            ])
            ->recordActions([
                Action::make('verify')
                    ->label('Setujui')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->authorize('verify')
                    ->visible(fn (User $record): bool => $record->status !== User::STATUS_VERIFIED)
                    ->requiresConfirmation()
                    ->modalHeading('Setujui verifikasi warga?')
                    ->modalDescription('Warga akan mendapat akses penuh ke layanan desa.')
                    ->action(function (User $record): void {
                        $record->update(['status' => User::STATUS_VERIFIED]);
                        AuditLogger::record('auth.user.verified', $record, 'Operator menyetujui verifikasi warga.');
                        Notification::make()->success()->title('Warga terverifikasi')->body($record->name)->send();
                    }),
                Action::make('reject')
                    ->label('Tolak')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->authorize('reject')
                    ->visible(fn (User $record): bool => $record->status !== User::STATUS_REJECTED)
                    ->requiresConfirmation()
                    ->modalHeading('Tolak verifikasi warga?')
                    ->action(function (User $record): void {
                        $record->update(['status' => User::STATUS_REJECTED]);
                        AuditLogger::record('auth.user.rejected', $record, 'Operator menolak verifikasi warga.');
                        Notification::make()->warning()->title('Verifikasi ditolak')->body($record->name)->send();
                    }),
                EditAction::make()
                    ->authorize('update'),
            ])
            ->emptyStateIcon('heroicon-o-users')
            ->emptyStateHeading('Belum ada warga');
    }
}
