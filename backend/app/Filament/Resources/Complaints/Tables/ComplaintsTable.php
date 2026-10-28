<?php

namespace App\Filament\Resources\Complaints\Tables;

use App\Models\Complaint;
use App\Services\ComplaintWorkflow;
use App\Support\ComplaintStatus;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Antrean pengaduan petugas (PEMBANGUNAN.md 4.5): proses -> selesai, atau tolak (wajib alasan),
 * plus balasan petugas. Semua transisi & balasan lewat ComplaintWorkflow (catat kronologi + audit).
 */
class ComplaintsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')->label('Pelapor')->searchable(),
                TextColumn::make('title')->label('Judul')->searchable()->limit(40),
                TextColumn::make('category')
                    ->label('Kategori')
                    ->formatStateUsing(fn (string $state): string => config('desa.complaints.categories')[$state] ?? $state),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable()
                    ->color(fn (string $state): string => match ($state) {
                        ComplaintStatus::DONE => 'success',
                        ComplaintStatus::REJECTED => 'danger',
                        ComplaintStatus::IN_PROGRESS => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ComplaintStatus::label($state)),
                TextColumn::make('submitted_at')->label('Diajukan')->dateTime('d M Y H:i')->sortable(),
            ])
            ->defaultSort('submitted_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(self::statusOptions()),
                SelectFilter::make('category')
                    ->label('Kategori')
                    ->options(fn (): array => config('desa.complaints.categories')),
            ])
            ->recordActions([
                ViewAction::make()->authorize('view'),
                self::advance('startProcess', 'Mulai Proses', ComplaintStatus::ACCEPTED, ComplaintStatus::IN_PROGRESS),
                self::advance('resolve', 'Tandai Selesai', ComplaintStatus::IN_PROGRESS, ComplaintStatus::DONE, noteField: true),
                self::rejectAction(),
                self::replyAction(),
            ])
            ->emptyStateIcon('heroicon-o-megaphone')
            ->emptyStateHeading('Tidak ada pengaduan');
    }

    /** @return array<string,string> */
    private static function statusOptions(): array
    {
        return collect(ComplaintStatus::ALL)
            ->mapWithKeys(fn (string $s): array => [$s => ComplaintStatus::label($s)])
            ->all();
    }

    private static function advance(string $name, string $label, string $from, string $to, bool $noteField = false): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon('heroicon-o-arrow-right-circle')
            ->color('success')
            ->authorize('process')
            ->visible(fn (Complaint $record): bool => $record->status === $from)
            ->requiresConfirmation()
            ->modalHeading($label)
            ->modalSubheading(fn (Complaint $record): string => "Pengaduan \"{$record->title}\" -> " . ComplaintStatus::label($to) . '.')
            ->schema($noteField ? [self::noteField(false)] : [])
            ->action(fn (Complaint $record, array $data) => self::doTransition($record, $to, $data['note'] ?? null));
    }

    private static function rejectAction(): Action
    {
        return Action::make('reject')
            ->label('Tolak')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->authorize('process')
            ->visible(fn (Complaint $record): bool => !ComplaintStatus::isClosed($record->status))
            ->schema([self::noteField(true)])
            ->modalHeading('Tolak pengaduan ini')
            ->modalDescription('Alasan penolakan akan dilihat warga — wajib diisi.')
            ->action(fn (Complaint $record, array $data) => self::doTransition($record, ComplaintStatus::REJECTED, $data['note']));
    }

    private static function replyAction(): Action
    {
        return Action::make('reply')
            ->label('Balas')
            ->icon('heroicon-o-chat-bubble-left-ellipsis')
            ->color('gray')
            ->authorize('reply')
            ->visible(fn (Complaint $record): bool => true)
            ->schema([self::noteField(true)])
            ->modalHeading('Balas pengaduan')
            ->action(function (Complaint $record, array $data): void {
                app(ComplaintWorkflow::class)->reply($record, $data['note'], auth()->user());
                Notification::make()->success()->title('Balasan terkirim')->send();
            });
    }

    private static function noteField(bool $required): Textarea
    {
        return Textarea::make('note')
            ->label('Pesan untuk warga')
            ->required($required)
            ->maxLength(2000);
    }

    private static function doTransition(Complaint $record, string $to, ?string $note): void
    {
        try {
            app(ComplaintWorkflow::class)->transition($record, $to, auth()->user(), $note);
        } catch (\InvalidArgumentException $e) {
            Notification::make()->danger()->title('Transisi ditolak')->body($e->getMessage())->send();

            return;
        }

        Notification::make()->success()->title('Pengaduan diproses')->body('Status: ' . ComplaintStatus::label($to))->send();
    }
}
