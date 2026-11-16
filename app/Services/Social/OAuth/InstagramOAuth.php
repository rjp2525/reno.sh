<?php

declare(strict_types=1);

namespace App\Services\Social\OAuth;

use App\Enums\SocialProvider;

/**
 * Instagram professional accounts linked to the user's Facebook Pages,
 * one SocialAccount per instagram_business_account. Publishing uses the
 * linked Page's (non-expiring) token.
 */
class InstagramOAuth extends MetaOAuth
{
    public function provider(): SocialProvider
    {
        return SocialProvider::INSTAGRAM;
    }

    public function accounts(array $token): array
    {
        $accounts = [];
        $fields = 'id,name,access_token,instagram_business_account{id,username,name,profile_picture_url}';

        foreach ($this->pages($token['access_token'], $fields) as $page) {
            $ig = $page['instagram_business_account'] ?? null;

            if (! is_array($ig) || empty($ig['id']) || empty($page['access_token'])) {
                continue;
            }

            $accounts[] = new ConnectedAccount(
                providerId: (string) $ig['id'],
                name: (string) ($ig['name'] ?? $ig['username'] ?? $page['name']),
                credentials: ['access_token' => (string) $page['access_token']],
                username: $ig['username'] ?? null,
                avatarUrl: $ig['profile_picture_url'] ?? null,
                meta: ['page_id' => (string) $page['id'], 'page_name' => $page['name'] ?? null],
            );
        }

        return $accounts;
    }
}
