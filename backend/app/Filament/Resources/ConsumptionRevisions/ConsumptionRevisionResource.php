<?php

namespace App\Filament\Resources\ConsumptionRevisions;

use App\Filament\Resources\ConsumptionRevisions\Pages\ListConsumptionRevisions;
use App\Filament\Resources\ConsumptionRevisions\Schemas\ConsumptionRevisionForm;
use App\Filament\Resources\ConsumptionRevisions\Tables\ConsumptionRevisionsTable;
use App\Models\ConsumptionRevision;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ConsumptionRevisionResource extends Resource
{
    protected static ?string $model = ConsumptionRevision::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return ConsumptionRevisionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ConsumptionRevisionsTable::configure($table);
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
            'index' => ListConsumptionRevisions::route('/'),
        ];
    }
}
