<?php

namespace App\Services\Integrations\Drivers;

use Illuminate\Support\Facades\Http;

class BrevoDriver extends AbstractDriver
{
    protected string $base = 'https://api.brevo.com/v3';

    protected function client(array $credentials)
    {
        return Http::withHeaders(['api-key' => $credentials['api_key'] ?? ''])->acceptJson();
    }

    public function verify(array $credentials): void
    {
        $this->send($this->client($credentials), 'get', $this->base.'/account');
    }

    public function resources(array $credentials): array
    {
        $data = $this->send($this->client($credentials), 'get', $this->base.'/contacts/lists', ['limit' => 50]);

        return collect($data['lists'] ?? [])
            ->map(fn ($list) => ['id' => (string) $list['id'], 'name' => $list['name'] ?? $list['id']])
            ->all();
    }

    public function sync(array $credentials, string $resourceId, array $data): void
    {
        $this->send($this->client($credentials), 'post', $this->base.'/contacts', [
            'email' => $data['email'],
            'updateEnabled' => true,
            'listIds' => [(int) $resourceId],
            'attributes' => array_filter([
                'FIRSTNAME' => $data['first_name'] ?? null,
                'LASTNAME' => $data['last_name'] ?? null,
                'SMS' => $data['phone'] ?? null,
            ]),
        ]);
    }
}
