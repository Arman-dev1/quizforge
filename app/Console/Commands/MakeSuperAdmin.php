<?php

namespace App\Console\Commands;

use App\Models\SuperAdmin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password as promptPassword;
use function Laravel\Prompts\text;

class MakeSuperAdmin extends Command
{
    protected $signature = 'app:make-super-admin
        {email? : Email address for the platform account}
        {--name= : Display name}
        {--password= : Password (prompted if omitted)}
        {--force : Skip the password strength check (local/dev use)}
        {--revoke : Deactivate this platform account instead}';

    protected $description = 'Create, update, or deactivate a platform (super admin) account';

    public function handle(): int
    {
        $email = $this->argument('email') ?: text(
            label: 'Email address',
            required: true,
        );

        if ($this->option('revoke')) {
            return $this->revoke($email);
        }

        $existing = SuperAdmin::where('email', $email)->first();

        $name = $this->option('name')
            ?: ($existing?->name ?: text(label: 'Name', required: true));

        // An existing account keeps its password unless a new one is given.
        $password = $this->option('password');

        if (! $password && ! $existing) {
            $password = promptPassword(label: 'Password', required: true);
        }

        // 12 characters is the default floor for an account that can read
        // every customer's data. --force exists for local setups; it warns
        // loudly rather than pretending the password is fine.
        $minLength = $this->option('force') ? 6 : 12;

        $validator = Validator::make(
            array_filter(['email' => $email, 'name' => $name, 'password' => $password]),
            [
                'email' => ['required', 'email', 'max:255'],
                'name' => ['required', 'string', 'max:255'],
                'password' => [$existing ? 'nullable' : 'required', 'string', 'min:'.$minLength],
            ],
            ['password.min' => "Platform passwords must be at least {$minLength} characters. Pass --force to allow a shorter one."],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        if ($password && strlen($password) < 12) {
            $this->warn('This password is short for an account with platform-wide access. Change it before going live.');
        }

        $admin = SuperAdmin::updateOrCreate(
            ['email' => $email],
            array_filter([
                'name' => $name,
                'password' => $password,
                'is_active' => true,
            ], fn ($value) => $value !== null),
        );

        $this->info($existing
            ? "Updated platform account for {$admin->name}."
            : "Created platform account for {$admin->name}.");
        $this->line('  Sign in at '.url('/super-admin'));

        return self::SUCCESS;
    }

    protected function revoke(string $email): int
    {
        $admin = SuperAdmin::where('email', $email)->first();

        if (! $admin) {
            $this->error("No platform account found for [{$email}].");

            return self::FAILURE;
        }

        // Deactivated rather than deleted: the audit trail stays intact.
        $admin->forceFill(['is_active' => false])->save();

        $this->info("{$admin->name} can no longer sign in to the platform panel.");

        return self::SUCCESS;
    }
}
