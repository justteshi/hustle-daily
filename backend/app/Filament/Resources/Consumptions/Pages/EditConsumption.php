<?php

namespace App\Filament\Resources\Consumptions\Pages;

use App\Filament\Resources\Consumptions\ConsumptionResource;
use App\Models\Consumption;
use App\Services\CorrectConsumption;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

class EditConsumption extends EditRecord
{
    protected static string $resource = ConsumptionResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Consumption $record */
        $record = $this->record;
        $data['owner_email'] = $record->user->email;
        $data['current_items'] = $record->items->map(fn ($item): string => $item->food_name.' · '.$item->brand.' — '.$item->quantity.' '.$item->unit.' ('.$item->calories.' kcal)')->implode("\n");
        $data['correction_items'] = [];

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(CorrectConsumption::class)->handle($record, auth()->user(), $data);
        } catch (ModelNotFoundException) {
            throw ValidationException::withMessages(['data.correction_items' => 'Choose foods belonging to this log owner.']);
        } catch (ValidationException $exception) {
            $errors = [];
            foreach ($exception->errors() as $field => $messages) {
                $errors['data.'.$field] = $messages;
            }

            throw ValidationException::withMessages($errors);
        }
    }
}
