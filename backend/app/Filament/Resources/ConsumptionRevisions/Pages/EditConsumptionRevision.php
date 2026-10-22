<?php

namespace App\Filament\Resources\ConsumptionRevisions\Pages;

use App\Filament\Resources\ConsumptionRevisions\ConsumptionRevisionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditConsumptionRevision extends EditRecord
{
    protected static string $resource = ConsumptionRevisionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
