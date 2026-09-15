<?php

namespace App\Filament\Resources\Subscriptions\Schemas;

use App\Filament\Resources\Users\UserResource;
use App\Models\Plan;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Laravel\Paddle\Subscription;
use Laravel\Paddle\Transaction;

/**
 * The whole billing picture for one customer, reached from any of their
 * subscriptions: who they are, what they are on now, every subscription they
 * have ever held, and every transaction against them.
 */
class SubscriptionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Customer')
                ->schema([
                    TextEntry::make('owner_name')
                        ->label('Account owner')
                        ->state(fn (Subscription $record) => $record->billable?->billingContact()?->name ?? '—')
                        ->url(fn (Subscription $record) => ($owner = $record->billable?->billingContact())
                            ? UserResource::getUrl('view', ['record' => $owner])
                            : null)
                        ->color('primary'),

                    TextEntry::make('owner_email')
                        ->label('Email')
                        ->state(fn (Subscription $record) => $record->billable?->billingContact()?->email ?? '—')
                        ->copyable(),

                    TextEntry::make('owner_id')
                        ->label('User ID')
                        ->badge()
                        ->color('gray')
                        ->state(fn (Subscription $record) => $record->billable?->billingContact()?->id ?? '—'),

                    TextEntry::make('workspace_name')
                        ->label('Workspace')
                        ->state(fn (Subscription $record) => $record->billable?->name ?? '—'),

                    TextEntry::make('billable_id')
                        ->label('Workspace ID')
                        ->badge()
                        ->color('gray'),

                    TextEntry::make('gateway_customer')
                        ->label('Gateway customer')
                        ->state(fn (Subscription $record) => $record->billable?->customer?->paddle_id ?? '—')
                        ->copyable(),
                ])
                ->columns(3),

            Section::make('Current subscription')
                ->schema([
                    TextEntry::make('plan')
                        ->label('Plan')
                        ->badge()
                        ->color('info')
                        ->state(fn (Subscription $record) => Plan::nameForPriceId($record->items->first()?->price_id) ?? '—'),

                    TextEntry::make('status')
                        ->badge()
                        ->color(fn (?string $state) => match ($state) {
                            Subscription::STATUS_ACTIVE => 'success',
                            Subscription::STATUS_TRIALING => 'info',
                            Subscription::STATUS_PAST_DUE => 'danger',
                            Subscription::STATUS_PAUSED => 'warning',
                            default => 'gray',
                        }),

                    TextEntry::make('paddle_id')->label('Gateway ID')->copyable(),
                    TextEntry::make('type')->label('Type')->badge()->color('gray'),
                    TextEntry::make('created_at')->label('Started')->dateTime('M j, Y H:i'),
                    TextEntry::make('trial_ends_at')->label('Trial ends')->dateTime('M j, Y')->placeholder('—'),
                    TextEntry::make('paused_at')->label('Paused')->dateTime('M j, Y')->placeholder('—'),
                    TextEntry::make('ends_at')
                        ->label('Cancels')
                        ->dateTime('M j, Y')
                        ->placeholder('—')
                        ->helperText(fn (Subscription $record) => $record->ends_at?->isFuture() ? 'Still active until then' : null),
                ])
                ->columns(4),

            Section::make('All subscriptions')
                ->description('Every subscription this customer has held, newest first.')
                ->schema([
                    RepeatableEntry::make('all_subscriptions')
                        ->hiddenLabel()
                        ->state(fn (Subscription $record) => static::siblingSubscriptions($record))
                        ->schema([
                            TextEntry::make('plan')->label('Plan')->badge()->color('info'),
                            TextEntry::make('status')
                                ->badge()
                                ->color(fn (?string $state) => match ($state) {
                                    Subscription::STATUS_ACTIVE => 'success',
                                    Subscription::STATUS_TRIALING => 'info',
                                    Subscription::STATUS_PAST_DUE => 'danger',
                                    Subscription::STATUS_PAUSED => 'warning',
                                    default => 'gray',
                                }),
                            TextEntry::make('paddle_id')->label('Gateway ID')->copyable(),
                            TextEntry::make('started')->label('Started'),
                            TextEntry::make('ends')->label('Cancels')->placeholder('—'),
                        ])
                        ->columns(5),
                ]),

            Section::make('Transactions')
                ->description('Payments recorded against this customer.')
                ->schema([
                    RepeatableEntry::make('all_transactions')
                        ->hiddenLabel()
                        ->state(fn (Subscription $record) => static::transactions($record))
                        ->schema([
                            TextEntry::make('invoice')->label('Invoice')->placeholder('—'),
                            TextEntry::make('status')
                                ->badge()
                                ->color(fn (?string $state) => match ($state) {
                                    Transaction::STATUS_COMPLETED, Transaction::STATUS_PAID, Transaction::STATUS_BILLED => 'success',
                                    Transaction::STATUS_PAST_DUE => 'danger',
                                    Transaction::STATUS_CANCELED => 'gray',
                                    default => 'warning',
                                }),
                            TextEntry::make('total')->label('Total'),
                            TextEntry::make('tax')->label('Tax')->placeholder('—'),
                            TextEntry::make('billed')->label('Billed'),
                        ])
                        ->columns(5),

                    TextEntry::make('lifetime_total')
                        ->label('Total paid')
                        ->weight('bold')
                        ->state(fn (Subscription $record) => static::lifetimeTotal($record)),
                ]),
        ]);
    }

    /** Every subscription belonging to the same customer. */
    protected static function siblingSubscriptions(Subscription $record): array
    {
        return Subscription::query()
            ->where('billable_type', $record->billable_type)
            ->where('billable_id', $record->billable_id)
            ->with('items')
            ->latest('id')
            ->get()
            ->map(fn (Subscription $subscription) => [
                'plan' => Plan::nameForPriceId($subscription->items->first()?->price_id) ?? '—',
                'status' => $subscription->status,
                'paddle_id' => $subscription->paddle_id,
                'started' => $subscription->created_at?->format('M j, Y') ?? '—',
                'ends' => $subscription->ends_at?->format('M j, Y'),
            ])
            ->all();
    }

    protected static function transactions(Subscription $record): array
    {
        return Transaction::query()
            ->where('billable_type', $record->billable_type)
            ->where('billable_id', $record->billable_id)
            ->latest('billed_at')
            ->get()
            ->map(fn (Transaction $transaction) => [
                'invoice' => $transaction->invoice_number,
                'status' => $transaction->status,
                'total' => static::money($transaction->total, $transaction->currency),
                'tax' => $transaction->tax ? static::money($transaction->tax, $transaction->currency) : null,
                'billed' => $transaction->billed_at?->format('M j, Y') ?? '—',
            ])
            ->all();
    }

    protected static function lifetimeTotal(Subscription $record): string
    {
        $transactions = Transaction::query()
            ->where('billable_type', $record->billable_type)
            ->where('billable_id', $record->billable_id)
            ->whereIn('status', [Transaction::STATUS_COMPLETED, Transaction::STATUS_PAID, Transaction::STATUS_BILLED])
            ->get();

        if ($transactions->isEmpty()) {
            return '—';
        }

        return static::money(
            $transactions->sum(fn (Transaction $t) => (int) $t->total),
            $transactions->first()->currency,
        );
    }

    /** Gateway amounts arrive in minor units (2900 = $29.00). */
    protected static function money(int|string|null $amount, ?string $currency): string
    {
        return number_format(((int) $amount) / 100, 2).' '.($currency ?? 'USD');
    }
}
