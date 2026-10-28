<?php

namespace App\Filament\Resources\Pbb\Tables;

use App\Models\PbbObject;
use App\Models\User;
use App\Services\PbbImportService;
use App\Support\PbbStatus;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Daftar objek pajak + import CSV database PBB (PEMBANGUNAN.md 4.3). Import idempoten dan
 * dicatat ke pbb_import_logs. Hanya operator/kades/admin yang boleh mengimpor.
 */
class PbbObjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nop')->label('NOP')->searchable()->copyable(),
                TextColumn::make('wp_name')->label('Nama WP')->searchable()->limit(25),
                TextColumn::make('address')->label('Alamat')->searchable()->limit(30)->placeholder('—'),
                TextColumn::make('rt')->label('RT/RW')->formatStateUsing(
                    fn (?string $state, PbbObject $record): string => trim(($state ?? '').'/'.($record->rw ?? '')) === '/' ? '—' : ($state ?? '-').'/'.($record->rw ?? '-')
                ),
                TextColumn::make('bills_count')->label('Tagihan')->sortable(),
                TextColumn::make('latest_year')->label('Tahun Terakhir')->placeholder('—')->default('-'),
            ])
            ->defaultSort('nop')
            ->filters([
                Filter::make('has_arrears')
                    ->label('Hanya punya tunggakan')
                    ->query(fn ($query) => $query->whereHas('bills', fn ($b) => $b->where('status', '!=', PbbStatus::BILL_PAID))),
                SelectFilter::make('rt')
                    ->label('RT')
                    ->options(fn (): array => PbbObject::query()
                        ->where('village_id', self::actor()?->village_id)
                        ->whereNotNull('rt')->distinct()->orderBy('rt')->pluck('rt', 'rt')->all()),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->headerActions([
                self::importAction(),
            ])
            ->emptyStateIcon('heroicon-o-receipt-percent')
            ->emptyStateHeading('Belum ada objek pajak — import CSV dulu');
    }

    /**
     * User terautentikasi sebagai App\Models\User (anotasi tipe untuk static analysis).
     */
    private static function actor(): ?User
    {
        $user = Filament::auth()->user();

        return $user instanceof User ? $user : null;
    }

    private static function importAction(): Action
    {
        return Action::make('import')
            ->label('Import CSV PBB')
            ->icon('heroicon-o-arrow-up-tray')
            ->color('gray')
            ->visible(fn (): bool => self::actor()?->hasAnyRole(['operator', 'kades', 'admin']) ?? false)
            ->modalHeading('Import database PBB dari CSV')
            ->modalDescription('Header wajib: nop, tahun, jumlah. Opsional: denda, nama_wp, alamat, rt, rw, luas_tanah, luas_bangunan. Baris yang sudah ada diperbarui tanpa mengubah status tagihan.')
            ->modalSubmitActionLabel('Import')
            ->schema([
                FileUpload::make('file')
                    ->label('Berkas CSV')
                    ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'text/comma-separated-values'])
                    ->maxSize(10240)
                    ->storeFiles(false)
                    ->required()
                    ->helperText('Maks 10 MB. Export dari Excel/Spreadsheet sebagai .csv.'),
            ])
            ->action(function (array $data): void {
                $file = $data['file'];
                // storeFiles(false) -> $file adalah TemporaryUploadedFile privat.
                $temporaryPath = $file->getRealPath();

                if (!$temporaryPath || !is_file($temporaryPath)) {
                    Notification::make()->danger()->title('Berkas tidak terbaca.')->send();

                    return;
                }

                $content = (string) file_get_contents($temporaryPath);

                $actor = self::actor();
                if (! $actor) {
                    @unlink($temporaryPath);
                    Notification::make()->danger()->title('Sesi berakhir, silakan masuk kembali.')->send();

                    return;
                }

                try {
                    $log = app(PbbImportService::class)->importFromString(
                        (int) $actor->village_id,
                        $actor,
                        $content,
                        $file->getClientOriginalName(),
                    );
                } catch (\Throwable $e) {
                    Notification::make()->danger()->title('Import gagal')->body($e->getMessage())->send();

                    return;
                } finally {
                    @unlink($temporaryPath);
                }

                $color = match ($log->status) {
                    'success' => 'success',
                    'partial' => 'warning',
                    default => 'danger',
                };

                Notification::make()->title('Import selesai')
                    ->body("{$log->rows_created} baru, {$log->rows_updated} diperbarui, {$log->rows_skipped} dilewati (status: {$log->status}).")
                    ->{$color}()
                    ->send();
            });
    }
}
