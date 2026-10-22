<?php

namespace App\Filament\Resources\MealCategories\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MealCategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('key'),
                TextColumn::make('user.email')->label('Owner'),
                TextColumn::make('sort_order')->sortable(),
                TextColumn::make('is_active')->label('Active'),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
