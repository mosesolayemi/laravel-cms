<?php

namespace Cms\Filament\Resources\Admins\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AdminForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('firstname')
                    ->label('First name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('lastname')
                    ->label('Last name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->minLength(8)
                    ->required(fn (string $operation): bool => $operation === 'create')
                    // Blank on edit keeps the stored hash instead of
                    // overwriting it with an empty string.
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->helperText(fn (string $operation): ?string => $operation === 'edit'
                        ? 'Leave blank to keep the current password.'
                        : null),
                Toggle::make('is_active')
                    ->label('Active')
                    ->helperText('Inactive administrators cannot sign in to the panel.')
                    ->default(true),
            ]);
    }
}
