<?php

declare(strict_types=1);

namespace App\Terminal;

enum ColorMode: string
{
    case None = 'none';
    case Basic = '16';
    case Ansi256 = '256';
    case TrueColor = 'truecolor';

    /**
     * Map a `?color=` query value to a mode. Null means "not specified".
     */
    public static function fromQuery(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }

        return match (strtolower(trim($value))) {
            '0', 'false', 'no', 'off', 'none', 'never' => self::None,
            '16', 'basic', '8' => self::Basic,
            '256', 'ansi256' => self::Ansi256,
            '', '1', 'true', 'yes', 'on', 'always', 'truecolor', '24bit' => self::TrueColor,
            default => null,
        };
    }
}
