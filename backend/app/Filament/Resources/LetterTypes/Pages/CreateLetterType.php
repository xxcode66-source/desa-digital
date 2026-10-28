<?php

namespace App\Filament\Resources\LetterTypes\Pages;

use App\Filament\Resources\LetterTypes\LetterTypeResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateLetterType extends CreateRecord
{
    protected static string $resource = LetterTypeResource::class;

    /**
     * Kades membuat jenis untuk desanya sendiri; admin pusat membuat jenis
     * bawaan (village_id NULL) yang berlaku untuk semua desa.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $actor = Filament::auth()->user();
        $data['village_id'] = ($actor && !$actor->isAdmin()) ? $actor->village_id : null;

        return parent::handleRecordCreation($data);
    }
}
