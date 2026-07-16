<?php

namespace App\Filament\Widgets;

use App\Models\Quiz;
use App\Models\QuizResponse;
use App\Models\User;
use App\Models\Workspace;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Users', number_format(User::count())),
            Stat::make('Workspaces', number_format(Workspace::count())),
            Stat::make('Quizzes', number_format(Quiz::withoutGlobalScope('workspace')->count())),
            Stat::make('Responses (30 days)', number_format(
                QuizResponse::withoutGlobalScope('workspace')
                    ->withTrashed()
                    ->where('started_at', '>=', now()->subDays(30))
                    ->count()
            )),
        ];
    }
}
