<?php

namespace App\Services\LanCore\Exceptions;

use RuntimeException;

class InvalidLanCoreUserException extends RuntimeException
{
    public function __construct(string $message = 'LanCore returned incomplete or invalid user data.')
    {
        parent::__construct($message);
    }
}
