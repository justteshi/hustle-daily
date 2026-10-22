<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($this->record->is_admin && ! ($data['is_admin'] ?? false) && User::where('is_admin', true)->count() <= 1) {
            throw ValidationException::withMessages(['data.is_admin' => 'The last administrator cannot be demoted.']);
        }

        return $data;
    }
}
