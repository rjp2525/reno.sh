<?php

namespace App\Http\Resources;

use App\Models\PrintOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PrintOrder */
class PrintOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'number' => $this->number,
            'status' => $this->status->value,
            'email' => $this->email,
            'name' => $this->name,
            'currency' => $this->currency,
            'subtotal' => $this->subtotal,
            'shipping' => $this->shipping,
            'tax' => $this->tax,
            'total' => $this->total,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'photo' => [
                    'id' => $item->photo_id ? (string) $item->photo_id : null,
                    'title' => $item->photo_title,
                    'thumbnail_url' => $item->photo?->thumbnail_url,
                ],
                'product' => [
                    'id' => $item->print_product_id ? (string) $item->print_product_id : null,
                    'name' => $item->product_name,
                    'size_label' => $item->product?->sizeLabel(),
                ],
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
            ])->values()),
            'shipping_address' => $this->shipping_address,
            'tracking' => $this->tracking ?? [],
            'prodigi_order_id' => $this->prodigi_order_id,
            'error' => $this->error,
            'paid_at' => $this->paid_at?->toIso8601ZuluString(),
            'submitted_at' => $this->submitted_at?->toIso8601ZuluString(),
            'shipped_at' => $this->shipped_at?->toIso8601ZuluString(),
            'created_at' => $this->created_at?->toIso8601ZuluString(),
        ];
    }
}
