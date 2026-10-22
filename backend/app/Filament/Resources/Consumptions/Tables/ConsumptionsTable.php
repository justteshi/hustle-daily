<?php

namespace App\Filament\Resources\Consumptions\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ConsumptionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('local_date')->date()->sortable(),
                TextColumn::make('eaten_at')->dateTime()->sortable(),
                TextColumn::make('user.email')->label('Owner')->searchable(),
                TextColumn::make('mealCategory.name')->label('Category'),
                TextColumn::make('source_name')->label('Saved meal'),
                TextColumn::make('calories')->label('Calories'),
                TextColumn::make('revisions_count')->counts('revisions')->label('Corrections'),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
