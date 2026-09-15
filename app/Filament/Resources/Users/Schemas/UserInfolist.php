<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\WorkspaceRole;
use App\Models\Quiz;
use App\Models\User;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Account')
                ->schema([
                    TextEntry::make('id')->label('User ID')->badge()->color('gray'),
                    TextEntry::make('name'),
                    TextEntry::make('email')->label('Email address')->copyable(),
                    TextEntry::make('email_verified_at')
                        ->label('Email verified')
                        ->dateTime('M j, Y H:i')
                        ->placeholder('Not verified'),
                    TextEntry::make('created_at')->label('Joined')->dateTime('M j, Y H:i'),
                    TextEntry::make('updated_at')->label('Last updated')->since(),
                ])
                ->columns(3),

            Section::make('Workspaces')
                ->description('Every workspace this person belongs to, and their role in it.')
                ->schema([
                    TextEntry::make('workspaces')
                        ->hiddenLabel()
                        ->state(fn (User $record) => $record->workspaces()->get()->map(function ($workspace) use ($record) {
                            $role = WorkspaceRole::from($workspace->pivot->role)->label();
                            $current = $record->current_workspace_id === $workspace->id ? ' · current' : '';

                            return "{$workspace->name} — {$role}{$current}";
                        })->all())
                        ->badge()
                        ->placeholder('Not a member of any workspace'),
                ]),

            Section::make('Activity')
                ->schema([
                    // Counted without the tenant scope: the panel is looking
                    // at someone else's data, not its own workspace.
                    TextEntry::make('quizzes_created')
                        ->label('Quizzes created')
                        ->state(fn (User $record) => Quiz::withoutGlobalScope('workspace')
                            ->where('created_by', $record->id)
                            ->count()),

                    TextEntry::make('workspaces_owned')
                        ->label('Workspaces owned')
                        ->state(fn (User $record) => $record->workspaces()
                            ->wherePivot('role', WorkspaceRole::Owner->value)
                            ->count()),

                    TextEntry::make('notification_preferences')
                        ->label('Email notifications')
                        ->state(fn (User $record) => collect(User::NOTIFICATION_DEFAULTS)
                            ->keys()
                            ->filter(fn (string $type) => $record->wantsNotification($type, 'mail'))
                            ->map(fn (string $type) => str($type)->replace('_', ' ')->title()->toString())
                            ->values()
                            ->all())
                        ->badge()
                        ->placeholder('None'),
                ])
                ->columns(3),
        ]);
    }
}
