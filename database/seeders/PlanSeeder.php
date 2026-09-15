<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /** Seed the managed plans from the shipped config defaults. */
    public function run(): void
    {
        $position = 0;

        foreach (config('plans') as $key => $plan) {
            Plan::query()->updateOrCreate(['key' => $key], [
                'name' => $plan['name'],
                'tagline' => $plan['tagline'] ?? null,
                'price' => $plan['price'] ?? 0,
                'period' => $plan['period'] ?? 'mo',
                'price_id' => $plan['price_id'] ?? null,
                'quizzes' => $plan['limits']['quizzes'] ?? null,
                'responses_per_month' => $plan['limits']['responses_per_month'] ?? null,
                'members' => $plan['limits']['members'] ?? null,
                'integrations' => $plan['flags']['integrations'] ?? false,
                'custom_code' => $plan['flags']['custom_code'] ?? false,
                'remove_branding' => $plan['flags']['remove_branding'] ?? false,
                'features' => $plan['features'] ?? [],
                'is_popular' => $plan['popular'] ?? false,
                'position' => $position++,
                'is_active' => true,
            ]);
        }
    }
}
