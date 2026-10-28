<?php

namespace App\Filament\Resources\Pbb\Tables;

use App\Models\PbbClaim;
use App\Services\PbbClaimService;
use App\Support\PbbStatus;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Antrean klaim NOP utk petugas (PEMBANGUNAN.md 4.3): setujui atau tolak (wajib alasan).
 * Aksi di-authorize lewat PbbClaimPolicy::review; penulisan status + audit lewat PbbClaimService.
 */
class PbbClaimsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')->label('Pemohon')->searchable(),
                TextColumn::make('object.nop')->label('NOP')->searchable()->copyable(),
                TextColumn::make('object.wp_name')->label('Nama WP Tercatat')->limit(22)->placeholder('—'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable()
                    ->color(fn (string $state): string => match ($state) {
                        PbbStatus::CLAIM_APPROVED => 'success',
                        PbbStatus::CLAIM_REJECTED => 'danger',
                        default => 'warning',
                    })
                    ->formatStateUsing(fn (string $state): string => PbbStatus::label($state)),
                TextColumn::make('created_at')->label('Diajukan')->dateTime('d M Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(self::statusOptions()),
            ])
            ->recordActions([
                ViewAction::make()->authorize('view'),
                self::approveAction(),
                self::rejectAction(),
            ])
            ->emptyStateIcon('heroicon-o-clipboard-document-list')
            ->emptyStateHeading('Tidak ada klaim NOP');
    }

    /** @return array<string,string> */
    private static function statusOptions(): array
    {
        return collect(PbbStatus::CLAIM_ALL)
            ->mapWithKeys(fn (string $s): array => [$s => PbbStatus::label($s)])
            ->all();
    }

    private static function approveAction(): Action
    {
        return Action::make('approve')
            ->label('Setujui')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->authorize('review')
            ->visible(fn (PbbClaim $record): bool => $record->status === PbbStatus::CLAIM_PENDING)
            ->requiresConfirmation()
            ->modalHeading('Setujui klaim kepemilikan NOP ini')
            ->modalDescription('Warga akan melihat NOP ini di daftar "NOP saya"-nya.')
            ->schema([Textarea::make('note')->label('Catatan (opsional)')->maxLength(1000)])
            ->action(function (PbbClaim $record, array $data): void {
                app(PbbClaimService::class)->approve($record, auth()->user(), $data['note'] ?? null);
                Notification::make()->success()->title('Klaim disetujui')->send();
            });
    }

    private static function rejectAction(): Action
    {
        return Action::make('reject')
            ->label('Tolak')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->authorize('review')
            ->visible(fn (PbbClaim $record): bool => $record->status === PbbStatus::CLAIM_PENDING)
            ->schema([
                Textarea::make('note')
                    ->label('Alasan penolakan')
                    ->required()
                    ->maxLength(1000)
                    ->helperText('Ditampilkan ke warga.'),
            ])
            ->modalHeading('Tolak klaim ini')
            ->modalDescription('Alasan penolakan wajib diisi agar warga paham.')
            ->action(function (PbbClaim $record, array $data): void {
                try {
                    app(PbbClaimService::class)->reject($record, auth()->user(), $data['note']);
                } catch (\InvalidArgumentException $e) {
                    Notification::make()->danger()->title('Penolakan gagal')->body($e->getMessage())->send();

                    return;
                }
                Notification::make()->success()->title('Klaim ditolak')->send();
            });
    }
}
