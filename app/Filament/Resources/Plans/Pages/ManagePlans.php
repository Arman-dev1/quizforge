<?php

namespace App\Filament\Resources\Plans\Pages;

use App\Filament\Resources\Plans\PlanResource;
use App\Models\Plan;
use Database\Seeders\PlanSeeder;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePlans extends ManageRecords
{
    protected static string $resource = PlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('seedDefaults')
                ->label('Load defaults')
                ->icon('heroicon-o-arrow-down-tray')
                ->visible(fn () => ! Plan::anyDefined())
                ->action(fn () => app(PlanSeeder::class)->run()),
        ];
    }
}
