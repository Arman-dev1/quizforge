<?php

namespace App\Services\Integrations\Drivers;

use App\Services\Integrations\Contracts\IntegrationDriver;
use App\Services\Integrations\IntegrationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;

abstract class AbstractDriver implements IntegrationDriver
{
    /** Standard contact fields most email tools share. */
    public function targetFields(): array
    {
        return [
            'email' => ['label' => 'Email address', 'required' => true],
            'first_name' => ['label' => 'First name'],
            'last_name' => ['label' => 'Last name'],
            'phone' => ['label' => 'Phone'],
        ];
    }

    /**
     * Run a request and translate transport / HTTP errors into a friendly
     * IntegrationException.
     */
    protected function send(PendingRequest $request, string $method, string $url, array $options = []): array
    {
        try {
            $response = $request->timeout(15)->{$method}($url, $options);
        } catch (ConnectionException $e) {
            throw new IntegrationException(__('Could not reach the provider. Please try again.'));
        }

        if ($response->status() === 401 || $response->status() === 403) {
            throw new IntegrationException(__('The credentials were rejected. Double-check them and try again.'));
        }

        if ($response->failed()) {
            throw new IntegrationException(__('The provider returned an error (:status).', ['status' => $response->status()]));
        }

        return $response->json() ?? [];
    }
}
