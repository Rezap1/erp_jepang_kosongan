<?php

namespace App\Exceptions;

use RuntimeException;

class ScheduleConflictException extends RuntimeException
{
    public function userMessage(): string
    {
        return parent::getMessage();
    }
}
