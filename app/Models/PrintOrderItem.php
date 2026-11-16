<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrintOrderItem extends Model
{
    use HasUlids;

    protected $fillable = [
        'print_order_id', 'photo_id', 'print_product_id', 'photo_title', 'product_name',
        'sku', 'attributes', 'quantity', 'unit_price',
    ];

    protected function casts(): array
    {
        return [
            'attributes' => 'array',
            'quantity' => 'integer',
            'unit_price' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PrintOrder::class, 'print_order_id');
    }

    public function photo(): BelongsTo
    {
        return $this->belongsTo(Photo::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(PrintProduct::class, 'print_product_id');
    }
}
