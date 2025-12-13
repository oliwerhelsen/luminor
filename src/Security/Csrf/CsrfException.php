<?php

declare(strict_types=1);

namespace Lumina\DDD\Security\Csrf;

use Exception;

/**
 * CSRF Exception
 *
 * Thrown when CSRF validation fails.
 */
class CsrfException extends Exception
{
    public function __construct(string $message = 'CSRF token mismatch.')
    {
        parent::__construct($message, 419); // 419 is Laravel's CSRF status code
    }
}
