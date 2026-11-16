<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PrintOrderStatus: string implements HasColor, HasLabel
{
    case PENDING_PAYMENT = 'pending_payment';
    case PAID = 'paid';
    case SUBMITTED = 'submitted';
    case IN_PRODUCTION = 'in_production';
    case SHIPPED = 'shipped';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case FAILED = 'failed';

    public function getLabel(): string
    {
        return str($this->value)->replace('_', ' ')->ucfirst()->toString();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PENDING_PAYMENT, self::CANCELLED => 'gray',
            self::PAID, self::SUBMITTED, self::IN_PRODUCTION => 'info',
            self::SHIPPED, self::COMPLETED => 'success',
            self::FAILED => 'danger',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::PAID, self::SUBMITTED, self::IN_PRODUCTION, self::SHIPPED, self::FAILED], true);
    }
}
