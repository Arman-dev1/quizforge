<?php

namespace App\Filament\Resources\QuizTemplates;

use App\Filament\Resources\PlatformResource;
use App\Filament\Resources\QuizTemplates\Pages\CreateQuizTemplate;
use App\Filament\Resources\QuizTemplates\Pages\EditQuizTemplate;
use App\Filament\Resources\QuizTemplates\Pages\ListQuizTemplates;
use App\Filament\Resources\QuizTemplates\Schemas\QuizTemplateForm;
use App\Filament\Resources\QuizTemplates\Tables\QuizTemplatesTable;
use App\Models\QuizTemplate;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class QuizTemplateResource extends PlatformResource
{
    protected static ?string $model = QuizTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookmark;

    protected static string|\UnitEnum|null $navigationGroup = 'Catalog';

    public static function form(Schema $schema): Schema
    {
        return QuizTemplateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return QuizTemplatesTable::configure($table);
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
            'index' => ListQuizTemplates::route('/'),
            'create' => CreateQuizTemplate::route('/create'),
            'edit' => EditQuizTemplate::route('/{record}/edit'),
        ];
    }
}
