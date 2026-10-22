<?php

namespace App\Filament\Resources\MealCategories\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class MealCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')->relationship('user', 'email')->required()->searchable()->disabledOn('edit'),
                TextInput::make('key')->required()->maxLength(40),
                TextInput::make('name')->required()->maxLength(100),
                TextInput::make('sort_order')->numeric()->required()->minValue(0)->maxValue(65535),
                Toggle::make('is_active')->default(true),
            ]);
    }
}
