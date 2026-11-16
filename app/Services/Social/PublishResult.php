<?php

declare(strict_types=1);

namespace App\Services\Social;

final readonly class PublishResult
{
    public function __construct(
        public string $providerPostId,
        public ?string $url = null,
    ) {}
}
