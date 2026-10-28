<?php

namespace App\Filament\Resources\Umkm\Tables;

use App\Models\Shop;
use App\Services\UmkmService;
use App\Support\UmkmStatus;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Antrean verifikasi & moderasi toko UMKM untuk petugas desa (PEMBANGUNAN.md 4.4 & 4.7).
 * Pelaku mendaftar lewat aplikasi; di sini operator/kades/admin menyetujui, menolak (wajib
 * alasan), atau menonaktifkan. Aksi di-authorize lewat ShopPolicy::review; transisi & audit
 * terpusat di UmkmService.
 */
class ShopsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Toko')->searchable()->limit(30),
                TextColumn::make('owner.name')->label('Pemilik')->searchable()->placeholder('—'),
                TextColumn::make('category')
                    ->label('Kategori')
                    ->formatStateUsing(fn (?string $state): string => config('desa.umkm.categories')[$state] ?? (string) $state),
                TextColumn::make('products_count')
                    ->label('Produk')
                    ->counts('products')
                    ->badge()
                    ->color('gray'),
                self::statusColumn(),
                TextColumn::make('rt')->label('RT/RW')->placeholder('—')
                    ->formatStateUsing(fn (Shop $record): string => trim((string) $record->rt . '/' . (string) $record->rw, '/')),
                TextColumn::make('created_at')->label('Diajukan')->dateTime('d M Y')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->label('Status')->options(self::statusOptions()),
                SelectFilter::make('category')->label('Kategori')
                    ->options(fn (): array => config('desa.umkm.categories')),
                Filter::make('hidden')
                    ->label('Disembunyikan')
                    ->query(fn (Builder $q) => $q->where('is_hidden', true)),
            ])
            ->recordActions([
                ViewAction::make()->authorize('view'),
                self::approveAction(),
                self::rejectAction(),
                self::suspendAction(),
            ])
            ->emptyStateIcon('heroicon-o-building-storefront')
            ->emptyStateHeading('Belum ada toko UMKM');
    }

    private static function statusColumn(): TextColumn
    {
        return TextColumn::make('status')
            ->label('Status')
            ->badge()
            ->sortable()
            ->color(fn (string $state): string => match ($state) {
                UmkmStatus::SHOP_ACTIVE => 'success',
                UmkmStatus::SHOP_REJECTED => 'danger',
                UmkmStatus::SHOP_SUSPENDED => 'warning',
                default => 'gray',
            })
            ->formatStateUsing(fn (string $state): string => UmkmStatus::label($state));
    }

    /** @return array<string,string> */
    private static function statusOptions(): array
    {
        return collect(UmkmStatus::SHOP_ALL)
            ->mapWithKeys(fn (string $s): array => [$s => UmkmStatus::label($s)])
            ->all();
    }

    private static function approveAction(): Action
    {
        return Action::make('approve')
            ->label('Setujui')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->authorize('review')
            ->visible(fn (Shop $record): bool => in_array($record->status, [UmkmStatus::SHOP_PENDING, UmkmStatus::SHOP_SUSPENDED], true))
            ->requiresConfirmation()
            ->modalHeading(fn (Shop $record): string => $record->status === UmkmStatus::SHOP_SUSPENDED ? 'Aktifkan kembali toko ini' : 'Setujui toko ini')
            ->modalDescription('Toko akan tampil di marketplace desa dan produknya bisa dilihat warga.')
            ->schema([Textarea::make('note')->label('Catatan (opsional)')->maxLength(1000)])
            ->action(function (Shop $record, array $data): void {
                self::guard(fn () => app(UmkmService::class)->approve($record, auth()->user(), $data['note'] ?? null), 'Toko disetujui.');
            });
    }

    private static function rejectAction(): Action
    {
        return Action::make('reject')
            ->label('Tolak')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->authorize('review')
            ->visible(fn (Shop $record): bool => $record->status === UmkmStatus::SHOP_PENDING)
            ->schema([
                Textarea::make('note')
                    ->label('Alasan penolakan')
                    ->required()
                    ->maxLength(1000)
                    ->helperText('Ditampilkan ke pemilik agar bisa memperbaiki & mengajukan ulang.'),
            ])
            ->modalHeading('Tolak pendaftaran toko ini')
            ->action(function (Shop $record, array $data): void {
                self::guard(fn () => app(UmkmService::class)->reject($record, auth()->user(), $data['note']), 'Toko ditolak.');
            });
    }

    private static function suspendAction(): Action
    {
        return Action::make('suspend')
            ->label('Nonaktifkan')
            ->icon('heroicon-o-pause-circle')
            ->color('warning')
            ->authorize('review')
            ->visible(fn (Shop $record): bool => $record->status === UmkmStatus::SHOP_ACTIVE)
            ->requiresConfirmation()
            ->modalHeading('Nonaktifkan toko ini')
            ->modalDescription('Toko & produknya sementara tidak tampil di marketplace.')
            ->schema([Textarea::make('note')->label('Catatan (opsional)')->maxLength(1000)])
            ->action(function (Shop $record, array $data): void {
                self::guard(fn () => app(UmkmService::class)->suspend($record, auth()->user(), $data['note'] ?? null), 'Toko dinonaktifkan.');
            });
    }

    /** Bungkus panggilan service: transisi tak sah -> notifikasi bahaya, sukses -> notifikasi hijau. */
    private static function guard(callable $run, string $successTitle): void
    {
        try {
            $run();
        } catch (\InvalidArgumentException $e) {
            Notification::make()->danger()->title('Aksi ditolak')->body($e->getMessage())->send();

            return;
        }

        Notification::make()->success()->title($successTitle)->send();
    }
}
