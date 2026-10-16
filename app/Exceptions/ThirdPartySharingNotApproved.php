<?php

namespace App\Exceptions;

use RuntimeException;

class ThirdPartySharingNotApproved extends RuntimeException
{
    public static function for(string $processor, string $reason): self
    {
        return new self("Data sharing with processor [{$processor}] is not allowed: {$reason}");
    }
}
