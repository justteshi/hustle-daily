<?php

namespace App\Filament\Resources\Consumptions\Schemas;

use App\Models\Consumption;
use App\Models\Food;
use App\Models\FoodPortion;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ConsumptionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('owner_email')->label('Owner')->disabled()->dehydrated(false),
                Select::make('meal_category_id')->label('Meal category')
                    ->options(fn (?Consumption $record): array => $record?->user?->mealCategories()->where('is_active', true)->orderBy('sort_order')->pluck('name', 'id')->all() ?? [])
                    ->required(),
                Textarea::make('notes')->maxLength(2000),
                Textarea::make('current_items')->label('Current immutable snapshots')->disabled()->dehydrated(false)->rows(5),
                Repeater::make('correction_items')->label('Replace all foods and recalculate (optional)')
                    ->helperText('Leave empty to retain the exact historical nutrition snapshot. To correct ingredients, enter every food again. Older custom portions must be selected explicitly; no conversion is guessed.')
                    ->schema([
                        Select::make('food_id')->label('Branded food')
                            ->options(fn (): array => Food::query()->with('user')->orderBy('name')->get()->mapWithKeys(fn (Food $food): array => [$food->id => $food->name.' · '.$food->brand.' ('.$food->user->email.')'])->all())
                            ->searchable()->required(),
                        TextInput::make('quantity')->numeric()->required()->minValue(0.001),
                        Select::make('unit')->options(['g' => 'g', 'ml' => 'ml', 'serving' => 'serving', 'portion' => 'portion'])->required(),
                        Select::make('portion_id')->label('Portion (when unit is portion)')
                            ->options(fn (): array => FoodPortion::query()->with('food')->get()->mapWithKeys(fn (FoodPortion $portion): array => [$portion->id => $portion->food->name.' · '.$portion->label])->all())
                            ->searchable(),
                    ])->columns(2)->defaultItems(0),
                Textarea::make('reason')->label('Reason for correction')->required()->minLength(5)->maxLength(2000),
            ]);
    }
}
