<?php

namespace App\Services\Integrations\Drivers;

use Illuminate\Support\Facades\Http;

class MailerLiteDriver extends AbstractDriver
{
    protected string $base = 'https://connect.mailerlite.com/api';

    public function targetFields(): array
    {
        return [
            'email' => ['label' => 'Email address', 'required' => true],
            'name' => ['label' => 'Name'],
            'last_name' => ['label' => 'Last name'],
            'phone' => ['label' => 'Phone'],
        ];
    }

    protected function client(array $credentials)
    {
        return Http::withToken($credentials['api_key'] ?? '')->acceptJson();
    }

    public function verify(array $credentials): void
    {
        $this->send($this->client($credentials), 'get', $this->base.'/groups', ['limit' => 1]);
    }

    public function resources(array $credentials): array
    {
        $data = $this->send($this->client($credentials), 'get', $this->base.'/groups', ['limit' => 100]);

        return collect($data['data'] ?? [])
            ->map(fn ($group) => ['id' => (string) $group['id'], 'name' => $group['name'] ?? $group['id']])
            ->all();
    }

    public function sync(array $credentials, string $resourceId, array $data): void
    {
        $this->send($this->client($credentials), 'post', $this->base.'/subscribers', [
            'email' => $data['email'],
            'groups' => [$resourceId],
            'fields' => array_filter([
                'name' => $data['name'] ?? null,
                'last_name' => $data['last_name'] ?? null,
                'phone' => $data['phone'] ?? null,
            ]),
        ]);
    }
}
