<?php

namespace App\Filament\Resources\ConsumptionRevisions\Pages;

use App\Filament\Resources\ConsumptionRevisions\ConsumptionRevisionResource;
use Filament\Resources\Pages\ListRecords;

class ListConsumptionRevisions extends ListRecords
{
    protected static string $resource = ConsumptionRevisionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
