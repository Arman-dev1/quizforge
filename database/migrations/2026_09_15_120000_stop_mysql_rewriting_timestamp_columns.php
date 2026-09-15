<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MySQL/MariaDB silently attaches DEFAULT CURRENT_TIMESTAMP ON UPDATE
 * CURRENT_TIMESTAMP to the *first* NOT NULL TIMESTAMP column of a table when
 * `explicit_defaults_for_timestamp` is off (still the default on plenty of
 * shared hosts). Three columns were caught by it, and each is one Eloquent
 * `update()` away from being rewritten to "now":
 *
 *  - `quiz_responses.started_at` — responses are saved per page, so the start
 *    time was reset on every page and again when the score was stamped. That
 *    zeroed completion durations, bunched the dashboard trend onto today, and
 *    let last month's responses count against this month's plan quota.
 *  - `workspace_invitations.expires_at` — touching an invitation expired it.
 *  - `transactions.billed_at` (Cashier's table) — corrupted billing history.
 *
 * DATETIME carries no such implicit behaviour and Laravel writes it exactly
 * the same way, so the columns change type and nothing else moves. SQLite has
 * no equivalent quirk, hence the driver guard.
 */
return new class extends Migration
{
    /** @var array<string, string> table => column */
    protected array $columns = [
        'quiz_responses' => 'started_at',
        'workspace_invitations' => 'expires_at',
        'transactions' => 'billed_at',
    ];

    public function up(): void
    {
        $this->modify('DATETIME NOT NULL');
    }

    public function down(): void
    {
        $this->modify('TIMESTAMP NOT NULL');
    }

    protected function modify(string $definition): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        foreach ($this->columns as $table => $column) {
            if (! Schema::hasColumn($table, $column)) {
                continue;
            }

            DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` {$definition}");
        }
    }
};
