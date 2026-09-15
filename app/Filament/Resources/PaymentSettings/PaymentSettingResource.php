<?php

namespace App\Filament\Resources\PaymentSettings;

use App\Filament\Resources\PaymentSettings\Pages\ManagePaymentSettings;
use App\Filament\Resources\PlatformResource;
use App\Models\PaymentSetting;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Where platform staff choose the payment gateway and enter its keys.
 *
 * One gateway is active at a time; both providers' credentials are kept, so
 * switching back does not mean re-entering keys. Secrets are stored
 * encrypted on the model.
 */
class PaymentSettingResource extends PlatformResource
{
    protected static ?string $model = PaymentSetting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|\UnitEnum|null $navigationGroup = 'Platform';

    protected static ?string $navigationLabel = 'Payments';

    protected static ?string $modelLabel = 'payment gateway';

    protected static ?string $recordTitleAttribute = 'provider';

    /** Singleton — one row for the whole platform. */
    public static function canCreate(): bool
    {
        return ! PaymentSetting::query()->exists();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Active gateway')
                    ->description('Only one gateway processes payments at a time. Keys for the other are kept.')
                    ->schema([
                        Radio::make('provider')
                            ->label('Payment gateway')
                            ->options([
                                PaymentSetting::PROVIDER_NONE => 'None — billing disabled',
                                PaymentSetting::PROVIDER_PADDLE => 'Paddle',
                                PaymentSetting::PROVIDER_STRIPE => 'Stripe',
                            ])
                            ->descriptions([
                                PaymentSetting::PROVIDER_NONE => 'Everyone stays on the Free plan and the billing page shows checkout as unavailable.',
                                PaymentSetting::PROVIDER_PADDLE => 'Merchant of record — Paddle handles VAT and invoicing. Fully wired.',
                                PaymentSetting::PROVIDER_STRIPE => 'Direct card payments. Keys are stored and validated; completing checkout needs the Stripe package installed.',
                            ])
                            ->default(PaymentSetting::PROVIDER_NONE)
                            ->live()
                            ->required(),

                        Toggle::make('test_mode')
                            ->label('Test / sandbox mode')
                            ->helperText('Use the sandbox environment of the gateway. Turn this off only with live keys.')
                            ->default(true),
                    ])
                    ->columnSpanFull(),

                Section::make('Paddle credentials')
                    ->description('From Paddle → Developer Tools → Authentication.')
                    ->visible(fn ($get) => $get('provider') === PaymentSetting::PROVIDER_PADDLE)
                    ->schema([
                        TextInput::make('paddle.seller_id')
                            ->label('Seller ID')
                            ->required()
                            ->helperText('The numeric seller / vendor id.'),

                        TextInput::make('paddle.client_side_token')
                            ->label('Client-side token')
                            ->required()
                            ->helperText('Safe to expose in the browser — it opens the checkout overlay.'),

                        TextInput::make('paddle.api_key')
                            ->label('API key')
                            ->password()
                            ->revealable()
                            ->required()
                            ->helperText('Server-side secret. Stored encrypted.'),

                        TextInput::make('paddle.webhook_secret')
                            ->label('Webhook secret')
                            ->password()
                            ->revealable()
                            ->helperText('Needed for subscription status updates to reach the app.'),

                        // The value to paste into Paddle's notification
                        // destination — saves looking it up in the routes.
                        Placeholder::make('paddle_webhook_url')
                            ->label('Webhook URL to give Paddle')
                            ->content(fn () => url('/paddle/webhook'))
                            ->helperText('Paddle → Notifications → New destination. It must be a public URL — localhost is not reachable from Paddle, so use your live domain or a tunnel while testing.')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Stripe credentials')
                    ->description('From the Stripe dashboard → Developers → API keys.')
                    ->visible(fn ($get) => $get('provider') === PaymentSetting::PROVIDER_STRIPE)
                    ->schema([
                        TextInput::make('stripe.publishable_key')
                            ->label('Publishable key')
                            ->required()
                            ->helperText('Starts with pk_test_ or pk_live_.'),

                        TextInput::make('stripe.secret_key')
                            ->label('Secret key')
                            ->password()
                            ->revealable()
                            ->required()
                            ->helperText('Starts with sk_test_ or sk_live_. Stored encrypted.'),

                        TextInput::make('stripe.webhook_secret')
                            ->label('Webhook signing secret')
                            ->password()
                            ->revealable()
                            ->helperText('Starts with whsec_.'),

                        Placeholder::make('stripe_webhook_url')
                            ->label('Webhook URL to give Stripe')
                            ->content(fn () => url('/stripe/webhook'))
                            ->helperText('Stripe → Developers → Webhooks. Must be a public URL. Note that Stripe checkout is not wired up yet — see the note on the gateway option above.')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('provider')
                    ->label('Gateway')
                    ->badge()
                    ->formatStateUsing(fn (PaymentSetting $record) => $record->providerLabel())
                    ->color(fn (PaymentSetting $record) => $record->provider === PaymentSetting::PROVIDER_NONE ? 'gray' : 'success'),

                IconColumn::make('test_mode')
                    ->label('Test mode')
                    ->boolean(),

                IconColumn::make('configured')
                    ->label('Ready')
                    ->boolean()
                    ->getStateUsing(fn (PaymentSetting $record) => $record->isConfigured())
                    ->tooltip(fn (PaymentSetting $record) => $record->isConfigured()
                        ? 'Checkout is available to customers.'
                        : 'Missing credentials — customers see checkout as unavailable.'),

                TextColumn::make('updated_at')->label('Updated')->dateTime()->sortable(),
            ])
            ->recordActions([
                EditAction::make()->label('Edit credentials'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePaymentSettings::route('/'),
        ];
    }
}
