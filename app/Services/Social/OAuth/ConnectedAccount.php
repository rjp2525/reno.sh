<?php

declare(strict_types=1);

namespace App\Services\Social\OAuth;

use Carbon\CarbonInterface;

/** A page/profile returned by an OAuth connect, ready to upsert as a SocialAccount. */
final readonly class ConnectedAccount
{
    public function __construct(
        public string $providerId,
        public string $name,
        public array $credentials,
        public ?string $username = null,
        public ?string $avatarUrl = null,
        public ?CarbonInterface $tokenExpiresAt = null,
        public array $meta = [],
    ) {}
}
