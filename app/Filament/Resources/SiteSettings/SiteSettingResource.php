<?php

namespace App\Filament\Resources\SiteSettings;

use App\Filament\Resources\SiteSettings\Pages\ManageSiteSettings;
use App\Models\SiteSetting;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SiteSettingResource extends Resource
{
    protected static ?string $model = SiteSetting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static ?string $navigationLabel = 'Landing page';

    protected static ?string $modelLabel = 'landing page';

    protected static ?string $recordTitleAttribute = 'id';

    /** Singleton — only one content row exists. */
    public static function canCreate(): bool
    {
        return ! SiteSetting::query()->exists();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // ── Brand & nav ──────────────────────────────
                TextInput::make('data.brand')->label('Brand name')->required()->maxLength(60),
                Repeater::make('data.nav')->label('Nav links')
                    ->schema([
                        TextInput::make('label')->required(),
                        TextInput::make('url')->required()->default('#'),
                    ])->columns(2)->reorderable()->collapsible()->columnSpanFull(),

                // ── Hero ─────────────────────────────────────
                TextInput::make('data.hero_badge')->label('Hero badge')->columnSpanFull(),
                TextInput::make('data.hero_title')->label('Hero title'),
                TextInput::make('data.hero_highlight')->label('Hero highlight (gradient words)'),
                Textarea::make('data.hero_subtitle')->label('Hero subtitle')->rows(2)->columnSpanFull(),
                TextInput::make('data.hero_primary_label')->label('Primary button'),
                TextInput::make('data.hero_secondary_label')->label('Secondary button'),
                TextInput::make('data.hero_secondary_url')->label('Secondary button link')->default('#how'),
                TextInput::make('data.hero_note')->label('Hero note')->columnSpanFull(),

                // ── Logos ────────────────────────────────────
                TextInput::make('data.logos_heading')->label('Logos heading')->columnSpanFull(),
                Repeater::make('data.logos')->label('Logos')
                    ->schema([
                        TextInput::make('name')->required(),
                        TextInput::make('icon')->helperText('Heroicon name, e.g. cube'),
                    ])->columns(2)->reorderable()->collapsible()->columnSpanFull(),

                // ── Features ─────────────────────────────────
                TextInput::make('data.features_eyebrow')->label('Features eyebrow'),
                TextInput::make('data.features_title')->label('Features title'),
                Textarea::make('data.features_subtitle')->label('Features subtitle')->rows(2)->columnSpanFull(),
                Repeater::make('data.features')->label('Feature cards')
                    ->schema([
                        TextInput::make('icon')->required()->helperText('Heroicon name'),
                        Select::make('tone')->options(['teal' => 'Teal', 'amber' => 'Amber'])->default('teal'),
                        TextInput::make('title')->required()->columnSpanFull(),
                        Textarea::make('text')->rows(2)->columnSpanFull(),
                    ])->columns(2)->reorderable()->collapsible()->itemLabel(fn (array $state): ?string => $state['title'] ?? null)->columnSpanFull(),

                // ── How it works ─────────────────────────────
                TextInput::make('data.how_eyebrow')->label('How-it-works eyebrow'),
                TextInput::make('data.how_title')->label('How-it-works title'),
                Repeater::make('data.steps')->label('Steps')
                    ->schema([
                        TextInput::make('number')->required(),
                        TextInput::make('title')->required(),
                        Textarea::make('text')->rows(2)->columnSpanFull(),
                    ])->columns(2)->reorderable()->collapsible()->itemLabel(fn (array $state): ?string => $state['title'] ?? null)->columnSpanFull(),

                // ── Testimonials ─────────────────────────────
                TextInput::make('data.testimonials_eyebrow')->label('Testimonials eyebrow'),
                TextInput::make('data.testimonials_title')->label('Testimonials title'),
                Repeater::make('data.testimonials')->label('Testimonials')
                    ->schema([
                        Textarea::make('quote')->required()->rows(3)->columnSpanFull(),
                        TextInput::make('name')->required(),
                        TextInput::make('role'),
                        TextInput::make('initials')->maxLength(3),
                    ])->columns(2)->reorderable()->collapsible()->itemLabel(fn (array $state): ?string => $state['name'] ?? null)->columnSpanFull(),

                // ── Pricing ──────────────────────────────────
                TextInput::make('data.pricing_eyebrow')->label('Pricing eyebrow'),
                TextInput::make('data.pricing_title')->label('Pricing title'),
                Textarea::make('data.pricing_subtitle')->label('Pricing subtitle')->rows(2)->columnSpanFull(),
                Repeater::make('data.plans')->label('Pricing plans')
                    ->schema([
                        TextInput::make('name')->required(),
                        TextInput::make('price')->required(),
                        TextInput::make('period')->default('/mo'),
                        TextInput::make('tagline'),
                        TextInput::make('cta_label')->label('Button label'),
                        Toggle::make('popular')->label('Highlight as most popular'),
                        Textarea::make('features')->label('Feature bullets (one per line)')->rows(5)->columnSpanFull()
                            ->formatStateUsing(fn ($state) => is_array($state) ? implode("\n", $state) : $state)
                            ->dehydrateStateUsing(fn ($state) => array_values(array_filter(array_map('trim', explode("\n", (string) $state))))),
                    ])->columns(2)->reorderable()->collapsible()->itemLabel(fn (array $state): ?string => $state['name'] ?? null)->columnSpanFull(),

                // ── FAQ ──────────────────────────────────────
                TextInput::make('data.faq_eyebrow')->label('FAQ eyebrow'),
                TextInput::make('data.faq_title')->label('FAQ title'),
                Repeater::make('data.faqs')->label('FAQ items')
                    ->schema([
                        TextInput::make('question')->required()->columnSpanFull(),
                        Textarea::make('answer')->required()->rows(3)->columnSpanFull(),
                    ])->reorderable()->collapsible()->itemLabel(fn (array $state): ?string => $state['question'] ?? null)->columnSpanFull(),

                // ── CTA ──────────────────────────────────────
                TextInput::make('data.cta_title')->label('CTA title')->columnSpanFull(),
                Textarea::make('data.cta_subtitle')->label('CTA subtitle')->rows(2)->columnSpanFull(),
                TextInput::make('data.cta_primary_label')->label('CTA primary button'),
                TextInput::make('data.cta_secondary_label')->label('CTA secondary button'),
                TextInput::make('data.cta_secondary_url')->label('CTA secondary link')->default('#pricing'),

                // ── Footer ───────────────────────────────────
                Textarea::make('data.footer_tagline')->label('Footer tagline')->rows(2)->columnSpanFull(),
                Repeater::make('data.footer_columns')->label('Footer columns')
                    ->schema([
                        TextInput::make('heading')->required(),
                        Textarea::make('links')->label('Links — "Label | URL" per line')->rows(4)->columnSpanFull()
                            ->formatStateUsing(fn ($state) => is_array($state)
                                ? collect($state)->map(fn ($l) => ($l['label'] ?? '').' | '.($l['url'] ?? '#'))->implode("\n")
                                : $state)
                            ->dehydrateStateUsing(fn ($state) => collect(explode("\n", (string) $state))
                                ->map(fn ($line) => trim($line))->filter()
                                ->map(function ($line) {
                                    [$label, $url] = array_pad(array_map('trim', explode('|', $line, 2)), 2, '#');

                                    return ['label' => $label, 'url' => $url ?: '#'];
                                })->values()->all()),
                    ])->reorderable()->collapsible()->itemLabel(fn (array $state): ?string => $state['heading'] ?? null)->columnSpanFull(),
                TextInput::make('data.footer_copyright')->label('Footer copyright')->columnSpanFull(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('data.brand')->label('Landing page')->formatStateUsing(fn ($state) => $state ?: 'Landing page content'),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->recordActions([
                EditAction::make()->label('Edit content'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSiteSettings::route('/'),
        ];
    }
}
