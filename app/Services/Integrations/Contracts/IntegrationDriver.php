<?php

namespace App\Services\Integrations\Contracts;

use App\Services\Integrations\IntegrationException;

interface IntegrationDriver
{
    /**
     * Make a real API request to confirm the credentials work.
     *
     * @throws IntegrationException on invalid credentials / network failure
     */
    public function verify(array $credentials): void;

    /**
     * Fetch the connectable resources (audiences / lists / forms).
     *
     * @return array<int, array{id: string, name: string}>
     *
     * @throws IntegrationException
     */
    public function resources(array $credentials): array;

    /**
     * The target fields a quiz field can be mapped onto.
     *
     * @return array<string, array{label: string, required?: bool}>
     */
    public function targetFields(): array;

    /**
     * Push one contact to the chosen resource.
     *
     * @param  array<string, string>  $data  keyed by target field (email, first_name, …)
     *
     * @throws IntegrationException
     */
    public function sync(array $credentials, string $resourceId, array $data): void;
}
