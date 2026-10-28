<?php

namespace App\Filament\Resources\Pbb\Tables;

use App\Models\PbbDepositItem;
use App\Services\PbbDepositService;
use App\Support\PbbStatus;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Worklist penilaian setoran utk bendahara (PEMBANGUNAN.md 4.3): ACC sebagian berarti
 * menyetujui/menolak per baris NOP. Transisi tagihan & audit dijaga PbbDepositService
 * (assertTreasurer menolak non-bendahara/salah desa).
 */
class PbbDepositItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('bill.object.nop')->label('NOP')->searchable()->copyable(),
                TextColumn::make('bill.year')->label('Tahun')->sortable(),
                TextColumn::make('bill_total')
                    ->label('Semestakan')
                    ->getStateUsing(fn (PbbDepositItem $r): float => (float) $r->bill?->total())
                    ->formatStateUsing(fn ($state): string => 'Rp ' . number_format((float) $state, 0, ',', '.')),
                TextColumn::make('amount_claimed')
                    ->label('Disetor')
                    ->formatStateUsing(fn ($state): string => 'Rp ' . number_format((float) $state, 0, ',', '.')),
                TextColumn::make('batch.title')->label('Slip')->limit(22)->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable()
                    ->color(fn (string $state): string => match ($state) {
                        PbbStatus::ITEM_APPROVED => 'success',
                        PbbStatus::ITEM_REJECTED => 'danger',
                        default => 'warning',
                    })
                    ->formatStateUsing(fn (string $state): string => PbbStatus::label($state)),
            ])
            ->defaultSort('status', 'asc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        PbbStatus::ITEM_PENDING => PbbStatus::label(PbbStatus::ITEM_PENDING),
                        PbbStatus::ITEM_APPROVED => PbbStatus::label(PbbStatus::ITEM_APPROVED),
                        PbbStatus::ITEM_REJECTED => PbbStatus::label(PbbStatus::ITEM_REJECTED),
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                self::approveAction(),
                self::rejectAction(),
            ])
            ->emptyStateIcon('heroicon-o-clipboard-document-check')
            ->emptyStateHeading('Tidak ada item setoran');
    }

    private static function approveAction(): Action
    {
        return Action::make('accApprove')
            ->label('ACC')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (PbbDepositItem $record): bool => $record->status === PbbStatus::ITEM_PENDING)
            ->requiresConfirmation()
            ->modalHeading('ACC setoran NOP ini')
            ->modalDescription('Tagihan menjadi lunas (verifikasi internal desa, bukan bukti pelunasan resmi).')
            ->schema([Textarea::make('note')->label('Catatan (opsional)')->maxLength(1000)])
            ->action(function (PbbDepositItem $record, array $data): void {
                self::guard(fn () => app(PbbDepositService::class)->approveItem($record, auth()->user(), $data['note'] ?? null), 'Item di-ACC, tagihan jadi lunas.');
            });
    }

    private static function rejectAction(): Action
    {
        return Action::make('accReject')
            ->label('Tolak')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (PbbDepositItem $record): bool => $record->status === PbbStatus::ITEM_PENDING)
            ->schema([
                Textarea::make('note')->label('Alasan penolakan')->required()->maxLength(1000)
                    ->helperText('Mis. uang tidak cocok / nominal kurang.'),
            ])
            ->modalHeading('Tolak setoran NOP ini')
            ->modalDescription('Tagihan akan kembali ke status belum bayar.')
            ->action(function (PbbDepositItem $record, array $data): void {
                self::guard(fn () => app(PbbDepositService::class)->rejectItem($record, auth()->user(), $data['note']), 'Item ditolak.');
            });
    }

    /** Jalankan aksi bendahara; tangkap error bisnis (non-bendahara/transisi tak sah) jadi notifikasi. */
    private static function guard(callable $fn, string $successMessage): void
    {
        try {
            $fn();
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            Notification::make()->danger()->title('Tidak diizinkan')->body($e->getMessage())->send();

            return;
        } catch (\InvalidArgumentException $e) {
            Notification::make()->danger()->title('Gagal')->body($e->getMessage())->send();

            return;
        }

        Notification::make()->success()->title($successMessage)->send();
    }
}
