<?php

namespace App\Filament\Resources\MealCategories\Pages;

use App\Filament\Resources\MealCategories\MealCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMealCategory extends CreateRecord
{
    protected static string $resource = MealCategoryResource::class;
}
