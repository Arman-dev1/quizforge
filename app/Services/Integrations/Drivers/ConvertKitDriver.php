<?php

namespace App\Services\Integrations\Drivers;

use Illuminate\Support\Facades\Http;

class ConvertKitDriver extends AbstractDriver
{
    protected string $base = 'https://api.convertkit.com/v3';

    public function targetFields(): array
    {
        return [
            'email' => ['label' => 'Email address', 'required' => true],
            'first_name' => ['label' => 'First name'],
        ];
    }

    public function verify(array $credentials): void
    {
        // A valid api_key returns the account's forms; an invalid one 401s.
        $this->send(Http::acceptJson(), 'get', $this->base.'/forms', ['api_key' => $credentials['api_key'] ?? '']);
    }

    public function resources(array $credentials): array
    {
        $data = $this->send(Http::acceptJson(), 'get', $this->base.'/forms', ['api_key' => $credentials['api_key'] ?? '']);

        return collect($data['forms'] ?? [])
            ->map(fn ($form) => ['id' => (string) $form['id'], 'name' => $form['name'] ?? $form['id']])
            ->all();
    }

    public function sync(array $credentials, string $resourceId, array $data): void
    {
        $this->send(Http::acceptJson(), 'post', $this->base."/forms/{$resourceId}/subscribe", array_filter([
            'api_key' => $credentials['api_key'] ?? '',
            'email' => $data['email'],
            'first_name' => $data['first_name'] ?? null,
        ]));
    }
}
