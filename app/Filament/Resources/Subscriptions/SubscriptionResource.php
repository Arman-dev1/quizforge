<?php

namespace App\Filament\Resources\Subscriptions;

use App\Filament\Resources\PlatformResource;
use App\Filament\Resources\Subscriptions\Pages\ListSubscriptions;
use App\Filament\Resources\Subscriptions\Pages\ViewSubscription;
use App\Filament\Resources\Subscriptions\Schemas\SubscriptionInfolist;
use App\Models\Plan;
use App\Models\Workspace;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Laravel\Paddle\Subscription;

/**
 * Customer subscriptions, read-only.
 *
 * Billing state is owned by the gateway — changing it here would only
 * desynchronise the two. Anything that mutates a subscription happens in the
 * gateway or on the customer's own billing page.
 *
 * The list shows one row per customer (their most recent subscription), so a
 * customer who has re-subscribed does not fill the table. Extra subscriptions
 * are not hidden: a customer holding more than one *active* subscription is
 * being billed twice, so the row flags it loudly and the detail page lists
 * every subscription and transaction.
 */
class SubscriptionResource extends PlatformResource
{
    protected static ?string $model = Subscription::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

    protected static string|\UnitEnum|null $navigationGroup = 'Customers';

    protected static ?string $navigationLabel = 'Subscriptions';

    protected static ?string $recordTitleAttribute = 'paddle_id';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SubscriptionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            /*
             | One row per billable: the newest subscription for each
             | customer. Scoped to the table, not getEloquentQuery(), so
             | route binding still resolves the older ones — the detail page
             | has to be reachable for every subscription, not just the
             | latest.
             */
            ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('id', function ($sub) {
                $sub->selectRaw('MAX(id)')
                    ->from('subscriptions')
                    ->groupBy('billable_type', 'billable_id');
            }))
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn (Subscription $record) => ViewSubscription::getUrl(['record' => $record]))
            ->emptyStateHeading('No subscriptions yet')
            ->emptyStateDescription('Paid subscriptions appear here once customers upgrade.')
            ->columns([
                TextColumn::make('billable.id')
                    ->label('Customer')
                    ->formatStateUsing(fn (Subscription $record) => $record->billable?->billingContact()?->name
                        ?? $record->billable?->name
                        ?? 'Unknown')
                    ->description(fn (Subscription $record) => trim(collect([
                        $record->billable?->billingContact()?->email,
                        $record->billable ? 'Workspace #'.$record->billable_id : null,
                    ])->filter()->join(' · ')))
                    ->searchable(query: fn ($query, string $search) => $query->whereHasMorph(
                        'billable',
                        [Workspace::class],
                        fn ($q) => $q->where('name', 'like', '%'.$search.'%')
                            ->orWhereHas('members', fn ($m) => $m->where('name', 'like', '%'.$search.'%')
                                ->orWhere('email', 'like', '%'.$search.'%')),
                    )),

                TextColumn::make('billable.name')
                    ->label('Workspace')
                    ->placeholder('—'),

                TextColumn::make('plan')
                    ->label('Plan')
                    ->badge()
                    ->color('info')
                    ->state(fn (Subscription $record) => Plan::nameForPriceId($record->items->first()?->price_id) ?? '—'),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        Subscription::STATUS_ACTIVE => 'success',
                        Subscription::STATUS_TRIALING => 'info',
                        Subscription::STATUS_PAST_DUE => 'danger',
                        Subscription::STATUS_PAUSED => 'warning',
                        Subscription::STATUS_CANCELED => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),

                // Two live subscriptions means two charges — surface it.
                TextColumn::make('duplicates')
                    ->label('')
                    ->badge()
                    ->color('danger')
                    ->state(fn (Subscription $record) => ($count = static::activeCountFor($record)) > 1
                        ? $count.' active'
                        : null)
                    ->tooltip('This customer holds more than one active subscription and is being billed for each.')
                    ->placeholder(''),

                TextColumn::make('ends_at')
                    ->label('Cancels')
                    ->dateTime('M j, Y')
                    ->placeholder('—')
                    ->description(fn (Subscription $record) => $record->ends_at && $record->ends_at->isFuture()
                        ? 'On grace period'
                        : null)
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Started')
                    ->dateTime('M j, Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    Subscription::STATUS_ACTIVE => 'Active',
                    Subscription::STATUS_TRIALING => 'Trialing',
                    Subscription::STATUS_PAST_DUE => 'Past due',
                    Subscription::STATUS_PAUSED => 'Paused',
                    Subscription::STATUS_CANCELED => 'Canceled',
                ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    /** How many live subscriptions this customer holds. */
    public static function activeCountFor(Subscription $subscription): int
    {
        return (int) DB::table('subscriptions')
            ->where('billable_type', $subscription->billable_type)
            ->where('billable_id', $subscription->billable_id)
            ->whereIn('status', [Subscription::STATUS_ACTIVE, Subscription::STATUS_TRIALING, Subscription::STATUS_PAST_DUE])
            ->count();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSubscriptions::route('/'),
            'view' => ViewSubscription::route('/{record}'),
        ];
    }
}
