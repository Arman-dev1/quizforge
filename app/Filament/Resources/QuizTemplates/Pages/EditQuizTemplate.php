<?php

namespace App\Filament\Resources\QuizTemplates\Pages;

use App\Filament\Resources\QuizTemplates\QuizTemplateResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditQuizTemplate extends EditRecord
{
    protected static string $resource = QuizTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
