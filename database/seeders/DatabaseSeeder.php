<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Everything a fresh install needs, and nothing it doesn't.
 *
 * No demo users, workspaces or quizzes are created: customers self-register,
 * and a personal workspace is created for them automatically on first request
 * (see App\Http\Middleware\SetCurrentWorkspace). Seeding fake accounts into a
 * production database is how test data ends up on a live site.
 *
 * Every seeder here is idempotent — safe to re-run after an upgrade.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PlanSeeder::class,        // Free + Pro, from config/plans.php
            TemplateSeeder::class,    // the global starter quiz templates
            SuperAdminSeeder::class,  // the first platform account
        ]);
    }
}
