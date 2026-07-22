<?php

namespace App\Services\Integrations;

use App\Services\Integrations\Contracts\IntegrationDriver;

class IntegrationManager
{
    public function exists(string $provider): bool
    {
        return config("integrations.providers.{$provider}") !== null;
    }

    /** @return array<string, mixed> */
    public function meta(string $provider): array
    {
        return config("integrations.providers.{$provider}")
            ?? throw new IntegrationException(__('Unknown integration.'));
    }

    public function driver(string $provider): IntegrationDriver
    {
        $class = config("integrations.providers.{$provider}.driver")
            ?? throw new IntegrationException(__('Unknown integration.'));

        return app($class);
    }
}
