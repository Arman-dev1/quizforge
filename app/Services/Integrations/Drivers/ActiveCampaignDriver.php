<?php

namespace App\Services\Integrations\Drivers;

use App\Services\Integrations\IntegrationException;
use Illuminate\Support\Facades\Http;

class ActiveCampaignDriver extends AbstractDriver
{
    protected function base(array $credentials): string
    {
        $url = rtrim($credentials['api_url'] ?? '', '/');

        if ($url === '' || ! str_starts_with($url, 'http')) {
            throw new IntegrationException(__('Enter your full ActiveCampaign API URL (https://your-account.api-us1.com).'));
        }

        return $url;
    }

    protected function client(array $credentials)
    {
        return Http::withHeaders(['Api-Token' => $credentials['api_key'] ?? ''])->acceptJson();
    }

    public function verify(array $credentials): void
    {
        $this->send($this->client($credentials), 'get', $this->base($credentials).'/api/3/users/me');
    }

    public function resources(array $credentials): array
    {
        $data = $this->send($this->client($credentials), 'get', $this->base($credentials).'/api/3/lists', ['limit' => 100]);

        return collect($data['lists'] ?? [])
            ->map(fn ($list) => ['id' => (string) $list['id'], 'name' => $list['name'] ?? $list['id']])
            ->all();
    }

    public function sync(array $credentials, string $resourceId, array $data): void
    {
        $client = $this->client($credentials);
        $base = $this->base($credentials);

        $contact = $this->send($client, 'post', $base.'/api/3/contact/sync', [
            'contact' => array_filter([
                'email' => $data['email'],
                'firstName' => $data['first_name'] ?? null,
                'lastName' => $data['last_name'] ?? null,
                'phone' => $data['phone'] ?? null,
            ]),
        ]);

        $contactId = $contact['contact']['id'] ?? null;

        if ($contactId) {
            $this->send($client, 'post', $base.'/api/3/contactLists', [
                'contactList' => [
                    'list' => (int) $resourceId,
                    'contact' => (int) $contactId,
                    'status' => 1,
                ],
            ]);
        }
    }
}
