<?php

declare(strict_types=1);

namespace App\Services\Social;

use RuntimeException;

class SocialPublishException extends RuntimeException
{
    public function __construct(string $message, public readonly bool $needsReauth = false, public readonly bool $retryable = false)
    {
        parent::__construct($message);
    }
}
