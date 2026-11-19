<?php

declare(strict_types=1);

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * A CSS hex color that always carries its leading "#". Values typed or pasted
 * without it ("15803D") are invalid CSS and render as no color at all.
 *
 * @implements CastsAttributes<string|null, string|null>
 */
class HexColor implements CastsAttributes
{
    public const PATTERN = '/^#?([0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/i';

    public static function normalize(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return preg_match(self::PATTERN, $value) ? '#'.ltrim($value, '#') : $value;
    }

    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return self::normalize($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return self::normalize($value);
    }
}
