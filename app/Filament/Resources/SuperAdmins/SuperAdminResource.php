<?php

namespace App\Filament\Resources\SuperAdmins;

use App\Filament\Resources\PlatformResource;
use App\Filament\Resources\SuperAdmins\Pages\ManageSuperAdmins;
use App\Models\SuperAdmin;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

/**
 * Platform staff accounts. Separate from customer Users on purpose — these
 * are the people who run QuizForge.
 */
class SuperAdminResource extends PlatformResource
{
    protected static ?string $model = SuperAdmin::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $navigationLabel = 'Platform staff';

    protected static ?string $modelLabel = 'platform account';

    protected static string|\UnitEnum|null $navigationGroup = 'Platform';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->required()->maxLength(255),

                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),

                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->minLength(12)
                    ->required(fn (string $operation) => $operation === 'create')
                    ->dehydrated(fn (?string $state) => filled($state))
                    ->helperText('At least 12 characters. Leave blank to keep the current password.'),

                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    ->helperText('Inactive accounts cannot sign in to this panel.'),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->weight('bold')
                    ->description(fn (SuperAdmin $record) => $record->email)
                    ->searchable(['name', 'email']),
                IconColumn::make('is_active')->boolean()->label('Active'),
                TextColumn::make('last_login_at')
                    ->label('Last sign-in')
                    ->dateTime('M j, Y H:i')
                    ->placeholder('Never')
                    ->sortable(),
                TextColumn::make('created_at')->dateTime('M j, Y')->label('Added')->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                // Nobody can delete the account they are signed in with.
                DeleteAction::make()
                    ->visible(fn (SuperAdmin $record) => $record->id !== Auth::guard('super_admin')->id()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSuperAdmins::route('/'),
        ];
    }
}
