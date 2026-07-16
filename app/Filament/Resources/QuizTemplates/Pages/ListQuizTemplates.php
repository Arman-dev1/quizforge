<?php

namespace App\Filament\Resources\QuizTemplates\Pages;

use App\Filament\Resources\QuizTemplates\QuizTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListQuizTemplates extends ListRecords
{
    protected static string $resource = QuizTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
