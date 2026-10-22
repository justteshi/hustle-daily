<?php

namespace App\Filament\Resources\Consumptions;

use App\Filament\Resources\Consumptions\Pages\EditConsumption;
use App\Filament\Resources\Consumptions\Pages\ListConsumptions;
use App\Filament\Resources\Consumptions\Schemas\ConsumptionForm;
use App\Filament\Resources\Consumptions\Tables\ConsumptionsTable;
use App\Models\Consumption;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ConsumptionResource extends Resource
{
    protected static ?string $model = Consumption::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return ConsumptionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ConsumptionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConsumptions::route('/'),
            'edit' => EditConsumption::route('/{record}/edit'),
        ];
    }
}
