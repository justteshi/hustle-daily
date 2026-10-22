<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->required()->maxLength(255),
                TextInput::make('email')->email()->required()->unique(ignoreRecord: true)->maxLength(255),
                Toggle::make('is_admin')->label('Can access admin'),
                Section::make('Settings')->relationship('settings')->schema([
                    Select::make('locale')->options(['bg' => 'Bulgarian', 'en' => 'English'])->required(),
                    TextInput::make('time_zone')->label('IANA time zone')->required()->maxLength(100),
                ]),
            ]);
    }
}
