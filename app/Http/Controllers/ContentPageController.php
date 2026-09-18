<?php

namespace App\Http\Controllers;

use App\Models\ContentPage;
use App\Models\SiteSetting;
use Illuminate\View\View;

class ContentPageController extends Controller
{
    public function show(string $slug): View
    {
        $page = ContentPage::published()->where('slug', $slug)->firstOrFail();

        return view('pages.show', [
            'page' => $page,
            'c' => SiteSetting::current()->content(),
            'footerPages' => ContentPage::inFooter()->get(),
            // Only the integrations page draws provider cards, and it reads
            // them from the same config the app itself uses — so the public
            // list can never drift from what the product actually supports.
            'providers' => $page->showsIntegrationCards() ? $this->providers() : [],
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    protected function providers(): array
    {
        return collect(config('integrations.providers', []))
            ->map(fn (array $provider, string $key) => [
                'key' => $key,
                'name' => $provider['name'] ?? $key,
                'description' => $provider['description'] ?? '',
                'color' => $provider['color'] ?? '#0d9488',
                'ink' => $provider['ink'] ?? '#ffffff',
                'initial' => $provider['initial'] ?? strtoupper(substr($provider['name'] ?? $key, 0, 1)),
                'popular' => (bool) ($provider['popular'] ?? false),
            ])
            ->sortByDesc('popular')
            ->values()
            ->all();
    }
}
