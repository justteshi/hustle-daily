<?php

namespace App\Filament\Resources\ConsumptionRevisions\Tables;

use App\Filament\Resources\Consumptions\ConsumptionResource;
use App\Models\ConsumptionRevision;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ConsumptionRevisionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->dateTime()->sortable(),
                TextColumn::make('consumption.local_date')->date()->label('Logged date'),
                TextColumn::make('user.email')->label('Owner'),
                TextColumn::make('actor.email')->label('Corrected by'),
                TextColumn::make('revision_number')->label('Revision'),
                TextColumn::make('reason')->limit(80),
                TextColumn::make('before_state.calories')->label('Before kcal'),
                TextColumn::make('after_state.calories')->label('After kcal'),
                TextColumn::make('consumption_id')->label('Log')->url(fn (ConsumptionRevision $record): string => ConsumptionResource::getUrl('edit', ['record' => $record->consumption_id])),
            ])
            ->filters([
                //
            ]);
    }
}
