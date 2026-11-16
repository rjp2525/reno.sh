<?php

declare(strict_types=1);

namespace App\Services\Social;

use App\Enums\SocialProvider;
use App\Services\Social\OAuth\FacebookOAuth;
use App\Services\Social\OAuth\InstagramOAuth;
use App\Services\Social\OAuth\LinkedInOAuth;
use App\Services\Social\OAuth\OAuthProvider;
use App\Services\Social\OAuth\ThreadsOAuth;
use App\Services\Social\OAuth\XOAuth;
use App\Services\Social\Providers\BlueskyPublisher;
use App\Services\Social\Providers\FacebookPublisher;
use App\Services\Social\Providers\InstagramPublisher;
use App\Services\Social\Providers\LinkedInPublisher;
use App\Services\Social\Providers\MastodonPublisher;
use App\Services\Social\Providers\ThreadsPublisher;
use App\Services\Social\Providers\XPublisher;
use Closure;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * Resolves the publisher (and OAuth connector) for a network.
 * Bind as a singleton; swap drivers in tests with extend().
 */
class SocialManager
{
    /** @var array<string, SocialPublisher> */
    private array $drivers = [];

    /** @var array<string, Closure(Container): SocialPublisher> */
    private array $custom = [];

    public function __construct(private readonly Container $container) {}

    public function driver(SocialProvider $provider): SocialPublisher
    {
        return $this->drivers[$provider->value] ??= isset($this->custom[$provider->value])
            ? ($this->custom[$provider->value])($this->container)
            : $this->container->make(match ($provider) {
                SocialProvider::FACEBOOK => FacebookPublisher::class,
                SocialProvider::INSTAGRAM => InstagramPublisher::class,
                SocialProvider::LINKEDIN => LinkedInPublisher::class,
                SocialProvider::X => XPublisher::class,
                SocialProvider::THREADS => ThreadsPublisher::class,
                SocialProvider::BLUESKY => BlueskyPublisher::class,
                SocialProvider::MASTODON => MastodonPublisher::class,
            });
    }

    /** OAuth connector for networks that connect through a consent screen. */
    public function oauth(SocialProvider $provider): OAuthProvider
    {
        return $this->container->make(match ($provider) {
            SocialProvider::FACEBOOK => FacebookOAuth::class,
            SocialProvider::INSTAGRAM => InstagramOAuth::class,
            SocialProvider::LINKEDIN => LinkedInOAuth::class,
            SocialProvider::X => XOAuth::class,
            SocialProvider::THREADS => ThreadsOAuth::class,
            default => throw new InvalidArgumentException("{$provider->getLabel()} is connected with manually entered credentials, not OAuth."),
        });
    }

    /**
     * Register a custom publisher factory (tests, alternative implementations).
     *
     * @param  Closure(Container): SocialPublisher  $factory
     */
    public function extend(SocialProvider $provider, Closure $factory): static
    {
        $this->custom[$provider->value] = $factory;
        unset($this->drivers[$provider->value]);

        return $this;
    }
}
