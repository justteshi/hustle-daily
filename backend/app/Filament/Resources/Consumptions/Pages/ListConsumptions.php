<?php

namespace App\Filament\Resources\Consumptions\Pages;

use App\Filament\Resources\Consumptions\ConsumptionResource;
use Filament\Resources\Pages\ListRecords;

class ListConsumptions extends ListRecords
{
    protected static string $resource = ConsumptionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
