<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Services\Platform\Impersonation;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
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
        ];
    }
}
