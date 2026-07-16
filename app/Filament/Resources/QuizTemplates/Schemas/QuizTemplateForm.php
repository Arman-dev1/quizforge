<?php

namespace App\Filament\Resources\QuizTemplates\Schemas;

use App\Enums\QuizType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class QuizTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('workspace_id')
                    ->relationship('workspace', 'name')
                    ->label('Workspace (leave empty for a global template)')
                    ->default(null),
                TextInput::make('name')
                    ->required()
                    ->maxLength(150),
                TextInput::make('description')
                    ->maxLength(500)
                    ->default(null),
                TextInput::make('category')
                    ->required()
                    ->maxLength(100),
                Select::make('type')
                    ->options(QuizType::class)
                    ->required(),
                Textarea::make('content')
                    ->required()
                    ->rows(20)
                    ->rule('json')
                    ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : $state)
                    ->dehydrateStateUsing(fn ($state) => json_decode((string) $state, true))
                    ->helperText('Template content as JSON: {"settings": {...}, "pages": [...]}')
                    ->columnSpanFull(),
            ]);
    }
}
