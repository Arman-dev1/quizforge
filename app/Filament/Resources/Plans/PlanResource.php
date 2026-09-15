<?php

namespace App\Filament\Resources\Plans;

use App\Filament\Resources\Plans\Pages\ManagePlans;
use App\Filament\Resources\PlatformResource;
use App\Models\Plan;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PlanResource extends PlatformResource
{
    protected static ?string $model = Plan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|\UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?string $navigationLabel = 'Plans';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('key')->required()->maxLength(50)->helperText('Machine key, e.g. free / pro. Keep "free" for the default plan.'),
                TextInput::make('name')->required()->maxLength(60),
                TextInput::make('tagline')->maxLength(120),
                TextInput::make('price')->numeric()->default(0)->prefix('$')->suffix('/mo')->required(),
                /*
                 | Price ids come from the gateway, not from us: create the
                 | product and its recurring price there first, then paste
                 | the PRICE id (not the product id) here.
                 */
                TextInput::make('price_id')
                    ->label('Paddle price ID')
                    ->placeholder('pri_01hxxxxxxxxxxxxxxxxxxxxxxx')
                    ->helperText('Paddle → Catalog → Products → your price. Starts with "pri_". Leave empty for the free plan.')
                    ->rule('nullable')
                    ->suffixIcon(fn (?string $state) => filled($state) && ! str_starts_with($state, 'pri_') ? Heroicon::OutlinedExclamationTriangle : null)
                    ->suffixIconColor('warning'),

                TextInput::make('stripe_price_id')
                    ->label('Stripe price ID')
                    ->placeholder('price_1Xxxxxxxxxxxxxxx')
                    ->helperText('Stripe → Products → your price. Starts with "price_". Only needed if you switch the gateway to Stripe.'),

                TextInput::make('quizzes')->numeric()->label('Quiz limit')->helperText('Empty = unlimited'),
                TextInput::make('responses_per_month')->numeric()->label('Responses / month')->helperText('Empty = unlimited'),
                TextInput::make('members')->numeric()->label('Seats (team members)')->helperText('Empty = unlimited. Set 1 for "just you".'),

                Toggle::make('integrations')->label('Integrations (paid feature)')->helperText('When off, workspaces on this plan cannot connect email integrations.'),
                Toggle::make('custom_code')->label('Custom CSS & JavaScript (paid feature)'),

                Toggle::make('remove_branding')->label('Remove QuizForge branding (paid feature)')->helperText('Lets workspaces on this plan hide the "Powered by" line on their published quizzes.'),

                Toggle::make('is_popular')->label('Highlight as most popular'),
                Toggle::make('is_active')->label('Active')->default(true),
                TextInput::make('position')->numeric()->default(0)->label('Sort order'),

                Textarea::make('features')->label('Pricing bullets (one per line)')->rows(6)->columnSpanFull()
                    ->formatStateUsing(fn ($state) => is_array($state) ? implode("\n", $state) : $state)
                    ->dehydrateStateUsing(fn ($state) => array_values(array_filter(array_map('trim', explode("\n", (string) $state))))),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('position')
            ->columns([
                TextColumn::make('name')->weight('bold')->description(fn (Plan $r) => $r->tagline),
                TextColumn::make('price')->money('USD')->sortable(),
                TextColumn::make('quizzes')->label('Quizzes')->placeholder('∞'),
                TextColumn::make('members')->label('Seats')->placeholder('∞'),
                IconColumn::make('integrations')->boolean()->label('Integrations'),
                IconColumn::make('custom_code')->boolean()->label('Custom code'),
                IconColumn::make('is_popular')->boolean()->label('Popular'),
                IconColumn::make('is_active')->boolean()->label('Active'),

                // A paid plan with no price id for the live gateway cannot
                // be bought — worth spotting from the list.
                TextColumn::make('checkout')
                    ->label('Checkout')
                    ->badge()
                    ->getStateUsing(fn (Plan $record) => match (true) {
                        ($record->price ?? 0) === 0 => 'Free',
                        filled($record->activePriceId()) => 'Ready',
                        default => 'No price ID',
                    })
                    ->color(fn (string $state) => match ($state) {
                        'Ready' => 'success',
                        'No price ID' => 'danger',
                        default => 'gray',
                    })
                    ->tooltip(fn (Plan $record) => filled($record->activePriceId()) || ($record->price ?? 0) === 0
                        ? null
                        : 'Add the price ID for the active gateway under Platform → Payments.'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePlans::route('/'),
        ];
    }
}
