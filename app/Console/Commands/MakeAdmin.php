<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MakeAdmin extends Command
{
    protected $signature = 'app:make-admin {email : Email of the user to promote} {--revoke : Remove admin access instead}';

    protected $description = 'Grant (or revoke) platform admin access for a user';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error("No user found with email [{$this->argument('email')}].");

            return self::FAILURE;
        }

        $user->forceFill(['is_admin' => ! $this->option('revoke')])->save();

        $this->info($this->option('revoke')
            ? "{$user->name} is no longer a platform admin."
            : "{$user->name} can now access the admin panel at /admin.");

        return self::SUCCESS;
    }
}
