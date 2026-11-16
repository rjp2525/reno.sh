<?php

namespace App\Models;

use App\Enums\PrintOrderStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property PrintOrderStatus $status
 */
class PrintOrder extends Model
{
    use HasUlids;

    protected $fillable = [
        'number', 'status', 'email', 'name', 'phone', 'currency', 'subtotal', 'shipping', 'tax', 'total',
        'shipping_address', 'tracking', 'stripe_checkout_session_id', 'stripe_payment_intent_id',
        'prodigi_order_id', 'error', 'paid_at', 'submitted_at', 'shipped_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PrintOrderStatus::class,
            'shipping_address' => 'array',
            'tracking' => 'array',
            'paid_at' => 'datetime',
            'submitted_at' => 'datetime',
            'shipped_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PrintOrder $order): void {
            $order->id ??= $order->newUniqueId();
            // Random tail of the ULID: unique without a counter race, short enough to read out loud.
            $order->number ??= 'RP-'.strtoupper(substr((string) $order->id, -8));
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(PrintOrderItem::class);
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', collect(PrintOrderStatus::cases())->filter->isOpen()->map->value);
    }

    public function formatMoney(int $amount): string
    {
        return (new \NumberFormatter('en_US', \NumberFormatter::CURRENCY))->formatCurrency($amount / 100, $this->currency);
    }
}
