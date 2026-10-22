<?php

namespace App\Filament\Resources\MealCategories\Pages;

use App\Filament\Resources\MealCategories\MealCategoryResource;
use Filament\Resources\Pages\EditRecord;

class EditMealCategory extends EditRecord
{
    protected static string $resource = MealCategoryResource::class;
}
