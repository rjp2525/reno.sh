<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum SocialProvider: string implements HasColor, HasIcon, HasLabel
{
    case FACEBOOK = 'facebook';
    case INSTAGRAM = 'instagram';
    case LINKEDIN = 'linkedin';
    case X = 'x';
    case THREADS = 'threads';
    case BLUESKY = 'bluesky';
    case MASTODON = 'mastodon';

    public function getLabel(): string
    {
        return match ($this) {
            self::FACEBOOK => 'Facebook',
            self::INSTAGRAM => 'Instagram',
            self::LINKEDIN => 'LinkedIn',
            self::X => 'X',
            self::THREADS => 'Threads',
            self::BLUESKY => 'Bluesky',
            self::MASTODON => 'Mastodon',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::FACEBOOK => 'info',
            self::INSTAGRAM => 'danger',
            self::LINKEDIN => 'info',
            self::X => 'gray',
            self::THREADS => 'gray',
            self::BLUESKY => 'info',
            self::MASTODON => 'primary',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::FACEBOOK => 'heroicon-o-users',
            self::INSTAGRAM => 'heroicon-o-camera',
            self::LINKEDIN => 'heroicon-o-briefcase',
            self::X => 'heroicon-o-hashtag',
            self::THREADS => 'heroicon-o-at-symbol',
            self::BLUESKY => 'heroicon-o-cloud',
            self::MASTODON => 'heroicon-o-globe-alt',
        };
    }

    /** Hex brand color for UI chips. */
    public function brandColor(): string
    {
        return match ($this) {
            self::FACEBOOK => '#1877F2',
            self::INSTAGRAM => '#E4405F',
            self::LINKEDIN => '#0A66C2',
            self::X => '#E7E9EA',
            self::THREADS => '#FFFFFF',
            self::BLUESKY => '#1185FE',
            self::MASTODON => '#6364FF',
        };
    }

    /**
     * Publishing constraints, mirrored to clients through the API so composers
     * can warn before submit.
     *
     * @return array{max_chars: int, max_media: int, requires_media: bool, max_video_mb: int}
     */
    public function limits(): array
    {
        return match ($this) {
            self::FACEBOOK => ['max_chars' => 63206, 'max_media' => 10, 'requires_media' => false, 'max_video_mb' => 1024],
            self::INSTAGRAM => ['max_chars' => 2200, 'max_media' => 10, 'requires_media' => true, 'max_video_mb' => 300],
            self::LINKEDIN => ['max_chars' => 3000, 'max_media' => 9, 'requires_media' => false, 'max_video_mb' => 200],
            self::X => ['max_chars' => 280, 'max_media' => 4, 'requires_media' => false, 'max_video_mb' => 512],
            self::THREADS => ['max_chars' => 500, 'max_media' => 10, 'requires_media' => false, 'max_video_mb' => 1024],
            self::BLUESKY => ['max_chars' => 300, 'max_media' => 4, 'requires_media' => false, 'max_video_mb' => 50],
            self::MASTODON => ['max_chars' => 500, 'max_media' => 4, 'requires_media' => false, 'max_video_mb' => 40],
        };
    }

    /** Character count the way the network counts it. */
    public function length(string $text): int
    {
        if ($this === self::X || $this === self::MASTODON) {
            // X (t.co wrapping) and Mastodon both count every URL as 23 characters.
            $text = (string) preg_replace('~https?://\S+~i', str_repeat('x', 23), $text);
        }

        return grapheme_strlen($text) ?: mb_strlen($text);
    }

    /** OAuth-connected (true) vs. credentials entered by hand (false). */
    public function usesOAuth(): bool
    {
        return ! in_array($this, [self::BLUESKY, self::MASTODON], true);
    }
}
