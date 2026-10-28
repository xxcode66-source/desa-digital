<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ScopedToVillage;
use App\Models\Complaint;
use App\Support\ComplaintStatus;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Daftar 6 pengaduan terbaru — biar beranda langsung menunjukkan aktivitas nyata.
 */
class PengaduanTerbaruWidget extends TableWidget
{
    use ScopedToVillage;

    protected static ?string $heading = 'Pengaduan Terbaru';

    protected static ?string $model = Complaint::class;

    protected static ?int $sort = 6;

    protected string|int|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->scoped(Complaint::query())->latest('submitted_at')->limit(6))
            ->columns([
                TextColumn::make('title')->label('Judul')->limit(45)->wrap(),
                TextColumn::make('category')->label('Kategori')->badge()->color('gray'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ComplaintStatus::label($state))
                    ->color(fn (string $state): string => match ($state) {
                        ComplaintStatus::DONE => 'success',
                        ComplaintStatus::IN_PROGRESS => 'info',
                        ComplaintStatus::REJECTED => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('submitted_at')->label('Tanggal')->dateTime('d M Y'),
            ])
            ->paginated(false)
            ->emptyStateIcon('heroicon-o-megaphone')
            ->emptyStateHeading('Belum ada pengaduan');
    }
}
