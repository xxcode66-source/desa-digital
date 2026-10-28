<?php

namespace App\Filament\Resources\LetterTypes\Pages;

use App\Filament\Resources\LetterTypes\LetterTypeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLetterType extends EditRecord
{
    protected static string $resource = LetterTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->authorize('delete')
                ->hidden(fn (): bool => $this->record->letters()->exists()),
        ];
    }
}
