<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Domain or API-level exception carrying an HTTP status code for JSON error responses.
 */
class ApiException extends \Exception
{
    /**
     * @param string $message Error message exposed (or mapped) to the client payload
     * @param int $code HTTP status code (e.g. 400, 404, 500)
     */
    public function __construct(string $message = '', int $code = 500, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
