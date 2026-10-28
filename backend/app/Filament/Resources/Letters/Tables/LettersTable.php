<?php

namespace App\Filament\Resources\Letters\Tables;

use App\Models\Letter;
use App\Models\LetterType;
use App\Services\LetterWorkflow;
use App\Support\LetterStatus;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Antrean surat staff desa (PEMBANGUNAN.md 4.2): sahkan RT -> verifikasi operator
 * -> tanda tangan kades, plus minta perbaikan per-field & penolakan wajib alasan.
 * Semua transisi lewat LetterWorkflow (log status + audit + penomoran otomatis).
 */
class LettersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')->label('Warga')->searchable(),
                TextColumn::make('type.name')->label('Jenis Surat'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable()
                    ->color(fn (string $state): string => match ($state) {
                        LetterStatus::DONE => 'success',
                        LetterStatus::REJECTED => 'danger',
                        LetterStatus::REPAIR => 'warning',
                        LetterStatus::SIGN => 'primary',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => LetterStatus::label($state)),
                TextColumn::make('number')->label('Nomor Surat')->placeholder('—')->searchable(),
                TextColumn::make('submitted_at')->label('Diajukan')->dateTime('d M Y H:i')->sortable(),
            ])
            ->defaultSort('submitted_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(array_combine(array_values(LetterStatus::ALL), array_values(array_map(
                        static fn (string $s): string => LetterStatus::label($s),
                        LetterStatus::ALL,
                    )))),
                SelectFilter::make('letter_type_id')
                    ->label('Jenis Surat')
                    ->options(fn (): array => LetterType::query()
                        ->where(fn ($q) => $q->whereNull('village_id')->orWhere('village_id', auth()->user()?->village_id))
                        ->pluck('name', 'id')->all()),
            ])
            ->recordActions([
                ViewAction::make()->authorize('view'),
                self::advance('rtForward', 'Teruskan ke Pengantar RT', LetterStatus::SUBMITTED, LetterStatus::RT, 'approveRt',
                    visible: fn (Letter $record): bool => $record->type?->needs_rt ?? false),
                self::advance('rtApprove', 'Sahkan & Lanjut ke Operator', LetterStatus::RT, LetterStatus::OPERATOR, 'approveRt'),
                self::advance('operatorVerify', 'Verifikasi Layak', LetterStatus::OPERATOR, LetterStatus::SIGN, 'process',
                    noteField: true),
                self::advance('finalize', 'Tanda Tangan & Selesai', LetterStatus::SIGN, LetterStatus::DONE, 'finalize',
                    description: 'Nomor surat dihasilkan otomatis anti-duplikat dan tautan verifikasi publik (QR) aktif.'),
                self::repairAction(),
                self::rejectAction(),
            ])
            ->emptyStateIcon('heroicon-o-document-text')
            ->emptyStateHeading('Tidak ada surat dalam antrean');
    }

    /** Aksi transisi maju standar dengan optional catatan. */
    private static function advance(
        string $name,
        string $label,
        string $from,
        string $to,
        string $policy,
        ?string $description = null,
        bool $noteField = false,
        ?callable $visible = null,
    ): Action {
        return Action::make($name)
            ->label($label)
            ->icon('heroicon-o-arrow-right-circle')
            ->color('success')
            ->authorize($policy)
            ->visible(fn (Letter $record): bool => $record->status === $from && ($visible === null || $visible($record)))
            ->requiresConfirmation()
            ->modalHeading($label)
            ->modalSubheading(fn (Letter $record): string => "Surat milik {$record->user?->name} lanjut ke: " . LetterStatus::label($to) . '.')
            ->modalDescription($description ?? 'Pastikan data sudah benar sebelum melanjutkan.')
            ->modalSubmitActionLabel('Ya, ' . strtolower($label))
            ->schema($noteField ? [self::noteField(false)] : [])
            ->action(fn (Letter $record, array $data) => self::doTransition($record, $to, $data['note'] ?? null));
    }

    /** Minta perbaikan: staff menandai field mana yang salah (per-field, PEMBANGUNAN.md 4.2). */
    private static function repairAction(): Action
    {
        return Action::make('requestRepair')
            ->label('Minta Perbaikan')
            ->icon('heroicon-o-pencil-square')
            ->color('warning')
            ->authorize('process')
            ->visible(fn (Letter $record): bool => in_array($record->status, [
                LetterStatus::SUBMITTED, LetterStatus::RT, LetterStatus::OPERATOR,
            ], true))
            ->schema([
                CheckboxList::make('fields')
                    ->label('Bagian yang perlu diperbaiki warga')
                    ->options(fn (Letter $record): array => collect($record->type?->form_schema_json ?? [])
                        ->mapWithKeys(fn (array $f): array => [$f['key'] => $f['label'] ?? $f['key']])
                        ->all())
                    ->required()
                    ->minItems(1),
                self::noteField(true),
            ])
            ->modalHeading('Minta warga memperbaiki data surat')
            ->action(function (Letter $record, array $data): void {
                self::doTransition($record, LetterStatus::REPAIR, $data['note'], null, $data['fields'] ?? []);
            });
    }

    /** Penolakan wajib alasan (divalidasi juga oleh LetterWorkflow). */
    private static function rejectAction(): Action
    {
        return Action::make('reject')
            ->label('Tolak Surat')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->authorize('process')
            ->visible(fn (Letter $record): bool => in_array($record->status, [
                LetterStatus::SUBMITTED, LetterStatus::RT, LetterStatus::OPERATOR,
            ], true))
            ->schema([self::noteField(true)])
            ->modalHeading('Tolak surat ini')
            ->modalDescription('Alasan penolakan akan dilihat warga — wajib diisi.')
            ->action(fn (Letter $record, array $data) => self::doTransition($record, LetterStatus::REJECTED, $data['note']));
    }

    private static function noteField(bool $required): Textarea
    {
        return Textarea::make('note')
            ->label('Catatan untuk warga')
            ->required($required)
            ->maxLength(1000);
    }

    private static function doTransition(Letter $record, string $to, ?string $note, ?Model $actor = null, array $flagged = []): void
    {
        try {
            $letter = app(LetterWorkflow::class)->transition(
                $record, $to, $actor ?? auth()->user(), $note, $flagged,
            );
        } catch (\InvalidArgumentException $e) {
            Notification::make()->danger()->title('Transisi ditolak')->body($e->getMessage())->send();

            return;
        }

        $body = $to === LetterStatus::DONE
            ? "Surat selesai dengan nomor {$letter->number}."
            : 'Status: ' . LetterStatus::label($to);

        Notification::make()->success()->title('Surat diproses')->body($body)->send();
    }
}
