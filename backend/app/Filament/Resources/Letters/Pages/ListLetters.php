<?php

namespace App\Filament\Resources\Letters\Pages;

use App\Filament\Resources\Letters\LetterResource;
use Filament\Resources\Pages\ListRecords;

class ListLetters extends ListRecords
{
    protected static string $resource = LetterResource::class;
}
