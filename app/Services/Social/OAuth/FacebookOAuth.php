<?php

declare(strict_types=1);

namespace App\Services\Social\OAuth;

use App\Enums\SocialProvider;

/** One SocialAccount per Facebook Page the user manages. */
class FacebookOAuth extends MetaOAuth
{
    public function provider(): SocialProvider
    {
        return SocialProvider::FACEBOOK;
    }

    public function accounts(array $token): array
    {
        $accounts = [];

        foreach ($this->pages($token['access_token'], 'id,name,username,access_token,picture{url},tasks') as $page) {
            if (empty($page['access_token'])) {
                continue;
            }

            $accounts[] = new ConnectedAccount(
                providerId: (string) $page['id'],
                name: (string) ($page['name'] ?? $page['id']),
                credentials: ['access_token' => (string) $page['access_token']],
                username: $page['username'] ?? null,
                avatarUrl: data_get($page, 'picture.data.url'),
                meta: ['tasks' => $page['tasks'] ?? []],
            );
        }

        return $accounts;
    }
}
