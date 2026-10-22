<?php

namespace App\Filament\Resources\MealCategories;

use App\Filament\Resources\MealCategories\Pages\CreateMealCategory;
use App\Filament\Resources\MealCategories\Pages\EditMealCategory;
use App\Filament\Resources\MealCategories\Pages\ListMealCategories;
use App\Filament\Resources\MealCategories\Schemas\MealCategoryForm;
use App\Filament\Resources\MealCategories\Tables\MealCategoriesTable;
use App\Models\MealCategory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MealCategoryResource extends Resource
{
    protected static ?string $model = MealCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return MealCategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MealCategoriesTable::configure($table);
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
            'index' => ListMealCategories::route('/'),
            'create' => CreateMealCategory::route('/create'),
            'edit' => EditMealCategory::route('/{record}/edit'),
        ];
    }
}
