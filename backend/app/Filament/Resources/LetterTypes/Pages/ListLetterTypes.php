<?php

namespace App\Filament\Resources\LetterTypes\Pages;

use App\Filament\Resources\LetterTypes\LetterTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLetterTypes extends ListRecords
{
    protected static string $resource = LetterTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah Jenis Surat')];
    }
}
