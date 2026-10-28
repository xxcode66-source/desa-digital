<?php

namespace App\Filament\Resources\Umkm\Pages;

use App\Filament\Resources\Umkm\ProductResource;
use Filament\Resources\Pages\ListRecords;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;
}
