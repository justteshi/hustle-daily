<?php

namespace App\Filament\Resources\Recipes\Schemas;

use App\Models\Food;
use App\Models\FoodPortion;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class RecipeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')->relationship('user', 'email')->required()->searchable()->disabledOn('edit'),
                TextInput::make('name')->required()->maxLength(255),
                TextInput::make('yield_servings')->numeric()->required()->minValue(0.001),
                Toggle::make('is_archived')->default(false),
                Repeater::make('ingredients')->relationship()->orderColumn('sort_order')->schema([
                    Select::make('food_id')->label('Branded food')
                        ->options(fn (): array => Food::query()->orderBy('name')->get()
                            ->mapWithKeys(fn (Food $food): array => [$food->id => $food->name.' · '.$food->brand.' ('.$food->user?->email.')'])->all())
                        ->searchable()->required(),
                    TextInput::make('quantity')->numeric()->required()->minValue(0.001),
                    Select::make('unit')->options(['g' => 'g', 'ml' => 'ml', 'serving' => 'serving', 'portion' => 'portion'])->required(),
                    Select::make('food_portion_id')->label('Food-specific portion (only for portion unit)')
                        ->options(fn (): array => FoodPortion::query()->with('food')->get()
                            ->mapWithKeys(fn (FoodPortion $portion): array => [$portion->id => $portion->food->name.' · '.$portion->label])->all())
                        ->searchable(),
                ])->columns(2)->minItems(1)->defaultItems(1),
            ]);
    }
}
