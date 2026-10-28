<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ScopedToVillage;
use App\Models\Village;
use Filament\Widgets\Widget;

/**
 * Banner beranda: identitas desa + siapa yang sedang masuk.
 * Menggantikan AccountWidget bawaan Filament ("Welcome / Sign out") yang terasa generik.
 */
class DesaHeaderWidget extends Widget
{
    use ScopedToVillage;

    protected static ?int $sort = 0;

    protected string|int|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.desa-header';

    public function villageName(): string
    {
        $actor = $this->actor();

        $id = ($actor && ! $actor->isAdmin() && $actor->village_id)
            ? $actor->village_id
            : Village::query()->value('id');

        return Village::find($id)?->name ?? 'Desa';
    }

    public function userName(): string
    {
        return $this->actor()?->name ?? 'Petugas';
    }

    public function userRole(): string
    {
        $roles = $this->actor()?->roles ?? collect();

        return $roles->isNotEmpty()
            ? $roles->pluck('label')->filter()->implode(', ')
            : 'Petugas Desa';
    }
}
