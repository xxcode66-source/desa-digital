<?php

namespace App\Filament\Widgets\Concerns;

use App\Models\User;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;

/**
 * Polanya sama dengan widget stats yang sudah ada (PbbStatsWidget/UmkmStatsWidget):
 * admin melihat lintas desa, petugas lainnya dibatasi ke desanya sendiri.
 */
trait ScopedToVillage
{
    protected function actor(): ?User
    {
        return Filament::auth()->user();
    }

    protected function scoped(Builder $query): Builder
    {
        $actor = $this->actor();

        if ($actor && ! $actor->isAdmin() && $actor->village_id) {
            $table = $query->getModel()->getTable();
            $query->where("{$table}.village_id", $actor->village_id);
        }

        return $query;
    }

    /** Hitung jumlah model dengan scope desa + filter opsional. */
    protected function countOf(string $model, ?Closure $filters = null): int
    {
        $query = $model::query();

        if ($filters) {
            $filters($query);
        }

        return $this->scoped($query)->count();
    }
}
