<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    /** Customers self-register; there is nothing to create from here. */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
