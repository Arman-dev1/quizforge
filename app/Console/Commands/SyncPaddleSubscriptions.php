<?php

namespace App\Console\Commands;

use App\Models\Workspace;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Laravel\Paddle\Cashier;
use Laravel\Paddle\Subscription;
use Throwable;

/**
 * Pull subscription state from Paddle and reconcile it locally.
 *
 * Normally webhooks keep the two in step. This exists for the cases where
 * they can't: local development (Paddle cannot reach 127.0.0.1), a webhook
 * that failed to deliver, or a destination that was misconfigured for a
 * while. Safe to re-run — everything is matched on the Paddle id.
 */
class SyncPaddleSubscriptions extends Command
{
    protected $signature = 'app:sync-subscriptions
        {--workspace= : Only sync this workspace id}';

    protected $description = 'Reconcile local subscription records with Paddle';

    public function handle(): int
    {
        $workspaces = Workspace::query()
            ->when($this->option('workspace'), fn ($q, $id) => $q->whereKey($id))
            ->whereHas('customer')
            ->with('customer')
            ->get();

        if ($workspaces->isEmpty()) {
            $this->warn('No workspaces have a Paddle customer yet — nothing to sync.');

            return self::SUCCESS;
        }

        $created = $updated = 0;

        foreach ($workspaces as $workspace) {
            $paddleId = $workspace->customer->paddle_id;

            try {
                $subscriptions = Cashier::api('GET', 'subscriptions', ['customer_id' => $paddleId])['data'] ?? [];
            } catch (Throwable $e) {
                $this->error("  {$workspace->name}: {$e->getMessage()}");

                continue;
            }

            if ($subscriptions === []) {
                $this->line("  {$workspace->name}: no subscriptions at Paddle");

                continue;
            }

            foreach ($subscriptions as $data) {
                $existing = Subscription::where('paddle_id', $data['id'])->first();

                $attributes = [
                    'type' => $data['custom_data']['subscription_type'] ?? Subscription::DEFAULT_TYPE,
                    'status' => $data['status'],
                    'trial_ends_at' => $data['status'] === Subscription::STATUS_TRIALING && ! empty($data['next_billed_at'])
                        ? Carbon::parse($data['next_billed_at'], 'UTC')
                        : null,
                    'paused_at' => ! empty($data['paused_at']) ? Carbon::parse($data['paused_at'], 'UTC') : null,
                    'ends_at' => ! empty($data['canceled_at'])
                        ? Carbon::parse($data['canceled_at'], 'UTC')
                        : (! empty($data['scheduled_change']['effective_at']) && ($data['scheduled_change']['action'] ?? null) === 'cancel'
                            ? Carbon::parse($data['scheduled_change']['effective_at'], 'UTC')
                            : null),
                ];

                if ($existing) {
                    $existing->update($attributes);
                    $subscription = $existing;
                    $updated++;
                } else {
                    $subscription = $workspace->subscriptions()->create($attributes + ['paddle_id' => $data['id']]);
                    $created++;
                }

                // Items are replaced wholesale: Paddle is the source of truth.
                $subscription->items()->delete();

                foreach ($data['items'] ?? [] as $item) {
                    $subscription->items()->create([
                        'product_id' => $item['price']['product_id'],
                        'price_id' => $item['price']['id'],
                        'status' => $item['status'],
                        'quantity' => $item['quantity'] ?? 1,
                    ]);
                }

                $this->line("  {$workspace->name}: {$data['id']} [{$data['status']}]");
            }
        }

        $this->info("Done — {$created} created, {$updated} updated.");

        return self::SUCCESS;
    }
}
