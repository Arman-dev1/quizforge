<?php

namespace App\Services\Integrations\Drivers;

use App\Services\Integrations\Contracts\IntegrationDriver;
use App\Services\Integrations\IntegrationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;

abstract class AbstractDriver implements IntegrationDriver
{
    /**
     * Validate a user-supplied provider base URL before we ever send a
     * request to it. Without this, a workspace editor can point a driver
     * at cloud metadata endpoints or anything else inside our network and
     * read the result back through the verify step (SSRF).
     */
    protected function guardBaseUrl(string $url, string $message): string
    {
        $url = rtrim(trim($url), '/');
        $parts = parse_url($url);

        $host = $parts['host'] ?? '';

        if (($parts['scheme'] ?? '') !== 'https' || $host === '' || isset($parts['user']) || isset($parts['port'])) {
            throw new IntegrationException($message);
        }

        if (! $this->isPublicHost($host)) {
            throw new IntegrationException($message);
        }

        return 'https://'.$host.rtrim($parts['path'] ?? '', '/');
    }

    /**
     * Reject IP literals in private/loopback/link-local space and any host
     * that isn't a normal public dotted domain. Deliberately no DNS lookup:
     * that would put a resolver call in the request path and still wouldn't
     * stop rebinding, which belongs at the HTTP-client layer.
     */
    protected function isPublicHost(string $host): bool
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return (bool) filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }

        // Must look like a public domain: labels plus a real TLD.
        if (! preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)*\.[a-z]{2,}$/i', $host)) {
            return false;
        }

        // Internal-only suffixes that do resolve on private networks.
        foreach (['.local', '.internal', '.localhost', '.home', '.lan', '.intranet'] as $suffix) {
            if (str_ends_with(strtolower($host), $suffix)) {
                return false;
            }
        }

        return true;
    }

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
