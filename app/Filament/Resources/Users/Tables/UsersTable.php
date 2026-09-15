<?php

namespace App\Filament\Resources\Users\Tables;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Services\Platform\Impersonation;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn (User $record) => UserResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                TextColumn::make('name')
                    ->weight('bold')
                    ->description(fn (User $record) => $record->email)
                    ->searchable(['name', 'email']),

                TextColumn::make('currentWorkspace.name')
                    ->label('Workspace')
                    ->placeholder('—')
                    ->searchable(),

                TextColumn::make('workspaces_count')
                    ->label('Workspaces')
                    ->counts('workspaces')
                    ->alignRight()
                    ->sortable(),

                TextColumn::make('email_verified_at')
                    ->label('Verified')
                    ->dateTime('M j, Y')
                    ->placeholder('Not verified')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Joined')
                    ->dateTime('M j, Y')
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('email_verified_at')
                    ->label('Email verified')
                    ->nullable(),
            ])
            ->recordActions([
                ViewAction::make(),

                /*
                 | Log in as this customer. Runs through Livewire (a
                 | CSRF-protected POST) rather than a link, because it starts
                 | a session — a GET that logs you in as someone else is a
                 | link an attacker could bait a signed-in admin into.
                 */
                Action::make('impersonate')
                    ->label('Log in as')
                    ->icon(Heroicon::OutlinedArrowRightEndOnRectangle)
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading(fn (User $record) => "Log in as {$record->name}?")
                    ->modalDescription('You will be signed in as this customer in this browser. Your platform session stays active, and a banner will let you switch back. This is recorded in the application log.')
                    ->modalSubmitActionLabel('Log in as customer')
                    ->action(function (User $record) {
                        $admin = Auth::guard('super_admin')->user();

                        abort_unless($admin, 403);

                        app(Impersonation::class)->start($admin, $record);

                        return redirect()->route('dashboard');
                    }),

                DeleteAction::make()
                    ->modalDescription(fn (User $record) => UserResource::deletionWarning($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
