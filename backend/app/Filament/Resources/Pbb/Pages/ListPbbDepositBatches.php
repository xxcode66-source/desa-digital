<?php

namespace App\Filament\Resources\Pbb\Pages;

use App\Filament\Resources\Pbb\PbbDepositBatchResource;
use Filament\Resources\Pages\ListRecords;

class ListPbbDepositBatches extends ListRecords
{
    protected static string $resource = PbbDepositBatchResource::class;
}
