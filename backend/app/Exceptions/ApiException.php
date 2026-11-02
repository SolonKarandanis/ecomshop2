<?php

namespace App\Exceptions;

use Exception;

/**
 * A failure the API answers with its own message and status, kept in the
 * exception code. bootstrap/app.php renders every one the same way, and logs
 * only the 5xx ones: a 4xx is an expected answer to the client (an empty cart,
 * a review they can't leave), not something an operator needs to look at.
 *
 * Anything that isn't an ApiException is a bug or an outage: let it reach the
 * handler, which reports it once with its stack trace and answers 500.
 */
abstract class ApiException extends Exception
{
    public function status(): int
    {
        return $this->getCode();
    }

    public function isServerError(): bool
    {
        return $this->status() >= 500;
    }
}
