<?php

namespace App\Filament\Resources\Umkm\Tables;

use App\Models\ProductReport;
use App\Services\UmkmService;
use App\Support\UmkmStatus;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Antrean moderasi laporan UMKM untuk petugas desa (PEMBANGUNAN.md 4.4 & 4.7). Warga melaporkan
 * produk/toko; di sini operator/kades/admin menandai `ditindak` (bisa sekalian membekukan toko)
 * atau `diabaikan`. Aksi di-authorize lewat ProductReportPolicy::handle; transisi & audit via UmkmService.
 */
class ProductReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reporter.name')->label('Pelapor')->searchable()->placeholder('—'),
                TextColumn::make('subject')
                    ->label('Dilaporkan')
                    ->placeholder('—')
                    ->getStateUsing(fn (ProductReport $r): string => $r->product?->name ?? $r->shop?->name ?? '(dihapus)'),
                TextColumn::make('shop.name')->label('Toko')->placeholder('—'),
                TextColumn::make('reason')->label('Alasan')->limit(48)->wrap(),
                self::statusColumn(),
                TextColumn::make('created_at')->label('Masuk')->dateTime('d M Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->label('Status')->options(self::statusOptions()),
            ])
            ->recordActions([
                ViewAction::make()->authorize('view'),
                self::resolveAction(),
                self::dismissAction(),
            ])
            ->emptyStateIcon('heroicon-o-flag')
            ->emptyStateHeading('Tidak ada laporan UMKM');
    }

    private static function statusColumn(): TextColumn
    {
        return TextColumn::make('status')
            ->label('Status')
            ->badge()
            ->sortable()
            ->color(fn (string $state): string => match ($state) {
                UmkmStatus::REPORT_RESOLVED => 'success',
                UmkmStatus::REPORT_DISMISSED => 'gray',
                default => 'danger',
            })
            ->formatStateUsing(fn (string $state): string => UmkmStatus::label($state));
    }

    /** @return array<string,string> */
    private static function statusOptions(): array
    {
        return collect(UmkmStatus::REPORT_ALL)
            ->mapWithKeys(fn (string $s): array => [$s => UmkmStatus::label($s)])
            ->all();
    }

    private static function resolveAction(): Action
    {
        return Action::make('resolve')
            ->label('Tindaklanjuti')
            ->icon('heroicon-o-shield-check')
            ->color('success')
            ->authorize('handle')
            ->visible(fn (ProductReport $record): bool => $record->status === UmkmStatus::REPORT_NEW)
            ->schema([
                Textarea::make('note')->label('Catatan penanganan')->maxLength(1000),
                Checkbox::make('hide_shop')
                    ->label('Bekukan toko terkait (tidak tampil di marketplace)')
                    ->helperText('Hanya berlaku bila toko sedang aktif.'),
            ])
            ->modalHeading('Tindaklanjuti laporan ini')
            ->action(function (ProductReport $record, array $data): void {
                self::guard(fn () => app(UmkmService::class)->handleReport(
                    $record,
                    auth()->user(),
                    UmkmStatus::REPORT_RESOLVED,
                    $data['note'] ?? null,
                    (bool) ($data['hide_shop'] ?? false),
                ), 'Laporan ditindaklanjuti.');
            });
    }

    private static function dismissAction(): Action
    {
        return Action::make('dismiss')
            ->label('Abaikan')
            ->icon('heroicon-o-x-mark')
            ->color('gray')
            ->authorize('handle')
            ->visible(fn (ProductReport $record): bool => $record->status === UmkmStatus::REPORT_NEW)
            ->requiresConfirmation()
            ->modalHeading('Abaikan laporan ini')
            ->modalDescription('Laporan dinilai tidak melanggar; toko/produk tetap tampil.')
            ->action(function (ProductReport $record): void {
                self::guard(fn () => app(UmkmService::class)->handleReport(
                    $record,
                    auth()->user(),
                    UmkmStatus::REPORT_DISMISSED,
                ), 'Laporan diabaikan.');
            });
    }

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
