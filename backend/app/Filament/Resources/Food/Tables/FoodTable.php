<?php

namespace App\Filament\Resources\Food\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FoodTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('brand')->searchable(),
                TextColumn::make('user.email')->label('Owner'),
                TextColumn::make('nutrition.calories')->label('Calories / basis'),
                TextColumn::make('nutrition.protein')->label('Protein / basis'),
                TextColumn::make('is_archived')->label('Archived'),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
