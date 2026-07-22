<?php

namespace App\Services\Integrations;

use RuntimeException;

/**
 * Thrown when a provider rejects credentials or an API call fails.
 * The message is safe to surface to the user.
 */
class IntegrationException extends RuntimeException {}
