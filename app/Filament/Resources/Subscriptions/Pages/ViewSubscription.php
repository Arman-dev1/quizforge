<?php

namespace App\Filament\Resources\Subscriptions\Pages;

use App\Filament\Resources\Subscriptions\SubscriptionResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;
use Laravel\Paddle\Subscription;

class ViewSubscription extends ViewRecord
{
    protected static string $resource = SubscriptionResource::class;

    public function getTitle(): string
    {
        $record = $this->getRecord();

        return $record->billable?->billingContact()?->name
            ?? $record->billable?->name
            ?? 'Subscription';
    }

    protected function getHeaderActions(): array
    {
        return [
            // Webhooks can miss (or, in local development, never arrive at
            // all). This pulls the current state straight from the gateway.
            Action::make('sync')
                ->label('Sync from gateway')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Re-read this customer from the payment gateway?')
                ->modalDescription('Fetches their current subscriptions and updates what is stored here. Nothing is sent to the gateway.')
                ->modalSubmitActionLabel('Sync now')
                ->action(function (Subscription $record) {
                    Artisan::call('app:sync-subscriptions', ['--workspace' => $record->billable_id]);

                    $this->refreshFormData([]);

                    Notification::make()
                        ->title('Synced with the gateway')
                        ->success()
                        ->send();
                }),
        ];
    }
}
