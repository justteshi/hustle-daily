<?php

namespace App\Filament\Resources\Food\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FoodForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')->relationship('user', 'email')->required()->searchable()->disabledOn('edit'),
                TextInput::make('name')->required()->maxLength(255),
                TextInput::make('brand')->required()->maxLength(255),
                TextInput::make('barcode')->maxLength(100),
                Toggle::make('is_archived')->default(false),
                Section::make('Nutrition')->relationship('nutrition')->schema([
                    TextInput::make('basis_amount')->numeric()->required()->minValue(0.001),
                    Select::make('basis_unit')->options(['g' => 'g', 'ml' => 'ml', 'serving' => 'serving'])->required(),
                    TextInput::make('calories')->numeric()->required()->minValue(0),
                    TextInput::make('protein')->numeric()->required()->minValue(0),
                    TextInput::make('carbohydrates')->numeric()->required()->minValue(0),
                    TextInput::make('fat')->numeric()->required()->minValue(0),
                    TextInput::make('fibre')->numeric()->minValue(0),
                    TextInput::make('sugar')->numeric()->minValue(0),
                    TextInput::make('salt')->numeric()->minValue(0),
                ]),
                Repeater::make('portions')->relationship()->schema([
                    TextInput::make('label')->required()->maxLength(100),
                    TextInput::make('base_amount')->numeric()->required()->minValue(0.001),
                    Select::make('base_unit')->options(['g' => 'g', 'ml' => 'ml', 'serving' => 'serving'])->required(),
                ])->columns(3)->defaultItems(0),
            ]);
    }
}
