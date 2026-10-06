<?php

namespace App\Exceptions;

use InvalidArgumentException;

final class AnnouncementAudienceValidationException extends InvalidArgumentException
{
    public function __construct(public readonly string $field, string $message)
    {
        parent::__construct($message);
    }
}
