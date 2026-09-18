<?php

namespace App\Filament\Resources\ContentPages;

use App\Filament\Resources\ContentPages\Pages\ManageContentPages;
use App\Filament\Resources\PlatformResource;
use App\Models\ContentPage;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The public documents: About, Terms, Privacy, Refunds, Integrations.
 *
 * Editing only — the set is seeded and each slug is routed, so creating or
 * deleting from here would produce a page with no URL or a dead link in the
 * footer. Unpublishing is the way to take one down.
 */
class ContentPageResource extends PlatformResource
{
    protected static ?string $model = ContentPage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|\UnitEnum|null $navigationGroup = 'Platform';

    protected static ?string $navigationLabel = 'Pages';

    protected static ?string $modelLabel = 'page';

    protected static ?string $recordTitleAttribute = 'title';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('Page title')
                    ->required()
                    ->maxLength(120)
                    ->columnSpanFull(),

                TextInput::make('nav_label')
                    ->label('Footer label')
                    ->helperText('Shorter text for the footer link. Falls back to the title.')
                    ->maxLength(40),

                TextInput::make('position')
                    ->label('Footer order')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(99)
                    ->default(0),

                Textarea::make('excerpt')
                    ->label('Summary')
                    ->helperText('Shown under the heading and used as the page description for search engines.')
                    ->rows(2)
                    ->maxLength(255)
                    ->columnSpanFull(),

                RichEditor::make('body')
                    ->label('Content')
                    ->helperText(fn (Get $get) => $get('slug') === ContentPage::INTEGRATIONS
                        ? 'Shown below the integration cards, which are generated automatically from the providers the app supports.'
                        : 'Headings, lists, bold, italics and links are supported.')
                    ->toolbarButtons([
                        'bold', 'italic', 'underline', 'strike',
                        'h2', 'h3',
                        'bulletList', 'orderedList',
                        'link', 'blockquote',
                        'undo', 'redo',
                    ])
                    ->columnSpanFull(),

                Toggle::make('is_published')
                    ->label('Published')
                    ->helperText('Unpublished pages return 404 and disappear from the footer.')
                    ->default(true),

                Toggle::make('show_in_footer')
                    ->label('Show in footer')
                    ->default(true),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('position')
            ->columns([
                TextColumn::make('title')
                    ->label('Page')
                    ->weight('bold')
                    ->description(fn (ContentPage $record) => '/page/'.$record->slug)
                    ->searchable(),

                IconColumn::make('is_published')
                    ->label('Published')
                    ->boolean(),

                IconColumn::make('show_in_footer')
                    ->label('In footer')
                    ->boolean(),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime('M j, Y H:i')
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageContentPages::route('/'),
        ];
    }
}
