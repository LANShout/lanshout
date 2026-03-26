<?php

namespace App\Services\LanCore\Exceptions;

use RuntimeException;

class LanCoreDisabledException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('LanCore integration is disabled.');
    }
}
