<?php

namespace App\Filament\Resources\Pbb\Pages;

use App\Filament\Resources\Pbb\PbbDepositItemResource;
use Filament\Resources\Pages\ListRecords;

class ListPbbDepositItems extends ListRecords
{
    protected static string $resource = PbbDepositItemResource::class;
}
