<?php

namespace Database\Seeders;

use App\Models\ContentPage;
use Illuminate\Database\Seeder;

/**
 * The public documents every install needs: About, Terms, Privacy, Refunds
 * and Integrations.
 *
 * Idempotent, and deliberately non-destructive: an existing page keeps the
 * body the owner wrote. Only pages that are missing get created, so running
 * this after an upgrade adds new documents without overwriting anyone's
 * edited policies.
 */
class ContentPageSeeder extends Seeder
{
    public function run(): void
    {
        foreach (ContentPage::starterPages() as $page) {
            ContentPage::firstOrCreate(
                ['slug' => $page['slug']],
                $page,
            );
        }
    }
}
