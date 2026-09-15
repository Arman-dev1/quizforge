<?php

namespace App\Filament\Resources\Users;

use App\Enums\WorkspaceRole;
use App\Filament\Resources\PlatformResource;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\Schemas\UserInfolist;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class UserResource extends PlatformResource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|\UnitEnum|null $navigationGroup = 'Customers';

    protected static ?string $recordTitleAttribute = 'name';

    // Users self-register, and the panel does not edit their details —
    // it is here to look them up, sign in as them, and remove them.
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
        return UserInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    /**
     * Spelled out on the delete confirmation: removing a customer can strand
     * a workspace with no owner, which is not obvious from a name in a list.
     */
    public static function deletionWarning(User $user): string
    {
        $orphaned = $user->workspaces()
            ->wherePivot('role', WorkspaceRole::Owner->value)
            ->get()
            ->filter(fn ($workspace) => $workspace->owners()->count() === 1);

        $base = 'This permanently deletes the account and removes them from every workspace.';

        if ($orphaned->isEmpty()) {
            return $base;
        }

        return $base.' They are the only owner of '
            .$orphaned->pluck('name')->map(fn ($name) => '"'.$name.'"')->join(', ', ' and ')
            .' — those workspaces will be left without an owner.';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'view' => ViewUser::route('/{record}'),
        ];
    }
}
