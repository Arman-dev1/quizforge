<?php

namespace Database\Seeders;

use App\Models\SuperAdmin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Creates the first platform (super admin) account.
 *
 * Credentials come from the environment. When no password is set we generate
 * one and print it, rather than shipping a known default — a seeder that
 * always creates "password" is a back door on every install that runs it.
 *
 * Idempotent: re-running never overwrites an existing account's password.
 */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('SUPER_ADMIN_EMAIL', 'admin@example.com');
        $name = env('SUPER_ADMIN_NAME', 'Platform Admin');

        if (SuperAdmin::where('email', $email)->exists()) {
            $this->command?->info("Platform account {$email} already exists — left untouched.");

            return;
        }

        $password = env('SUPER_ADMIN_PASSWORD');
        $generated = blank($password);

        if ($generated) {
            $password = Str::password(16, symbols: false);
        }

        SuperAdmin::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'is_active' => true,
        ]);

        $this->command?->newLine();
        $this->command?->info('Platform admin created.');
        $this->command?->line('  URL      : '.rtrim(config('app.url'), '/').'/super-admin');
        $this->command?->line('  Email    : '.$email);

        if ($generated) {
            $this->command?->line('  Password : '.$password);
            $this->command?->warn('  Save this now — it is not stored anywhere and will not be shown again.');
        } else {
            $this->command?->line('  Password : (the SUPER_ADMIN_PASSWORD you set in .env)');
        }

        $this->command?->newLine();
    }
}
