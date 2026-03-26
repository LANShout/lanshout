<?php

namespace App\Services\LanCore\Exceptions;

use RuntimeException;

class LanCoreRequestException extends RuntimeException
{
    public function __construct(string $message = 'Failed to communicate with LanCore.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
