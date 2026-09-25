<?php

namespace App\Exceptions;

use RuntimeException;

class PublicHolidayServiceUnavailableException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(
            'Public holiday service is currently unavailable.'
        );
    }
}
