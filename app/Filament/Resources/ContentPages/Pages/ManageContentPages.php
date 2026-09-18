<?php

namespace App\Filament\Resources\ContentPages\Pages;

use App\Filament\Resources\ContentPages\ContentPageResource;
use Database\Seeders\ContentPageSeeder;
use Filament\Resources\Pages\ManageRecords;

class ManageContentPages extends ManageRecords
{
    protected static string $resource = ContentPageResource::class;

    public function mount(): void
    {
        parent::mount();

        // An install that upgraded rather than seeded would land here with an
        // empty list and no way to create a page. Seeding is idempotent and
        // never overwrites a body somebody has already edited.
        app(ContentPageSeeder::class)->run();
    }
}
