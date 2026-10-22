<?php

namespace App\Filament\Resources\Recipes\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RecipesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('user.email')->label('Owner'),
                TextColumn::make('yield_servings'),
                TextColumn::make('ingredients_count')->counts('ingredients')->label('Ingredients'),
                TextColumn::make('is_archived')->label('Archived'),
            ])
            ->filters([
                //
            ])
            ->recordActions([EditAction::make()]);
    }
}
