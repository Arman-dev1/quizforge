<?php

namespace App\Services\Integrations\Drivers;

use App\Services\Integrations\IntegrationException;
use Illuminate\Support\Facades\Http;

class MailchimpDriver extends AbstractDriver
{
    protected function base(array $credentials): string
    {
        $key = $credentials['api_key'] ?? '';
        $dc = str_contains($key, '-') ? substr($key, strpos($key, '-') + 1) : '';

        if ($dc === '') {
            throw new IntegrationException(__('That does not look like a Mailchimp API key (it should end in "-usXX").'));
        }

        return "https://{$dc}.api.mailchimp.com/3.0";
    }

    protected function client(array $credentials)
    {
        return Http::withBasicAuth('quizforge', $credentials['api_key'] ?? '')->acceptJson();
    }

    public function verify(array $credentials): void
    {
        $this->send($this->client($credentials), 'get', $this->base($credentials).'/ping');
    }

    public function resources(array $credentials): array
    {
        $data = $this->send($this->client($credentials), 'get', $this->base($credentials).'/lists', ['count' => 100]);

        return collect($data['lists'] ?? [])
            ->map(fn ($list) => ['id' => (string) $list['id'], 'name' => $list['name'] ?? $list['id']])
            ->all();
    }

    public function sync(array $credentials, string $resourceId, array $data): void
    {
        $hash = md5(strtolower($data['email']));

        $this->send($this->client($credentials), 'put', $this->base($credentials)."/lists/{$resourceId}/members/{$hash}", [
            'email_address' => $data['email'],
            'status_if_new' => 'subscribed',
            'merge_fields' => array_filter([
                'FNAME' => $data['first_name'] ?? null,
                'LNAME' => $data['last_name'] ?? null,
                'PHONE' => $data['phone'] ?? null,
            ]),
        ]);
    }
}
