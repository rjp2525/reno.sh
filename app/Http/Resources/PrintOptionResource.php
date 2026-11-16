<?php

namespace App\Http\Resources;

use App\Models\PrintProduct;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PrintProduct */
class PrintOptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'product_id' => (string) $this->id,
            'name' => $this->name,
            'type' => $this->type->value,
            'type_label' => $this->type->getLabel(),
            'size_label' => $this->sizeLabel(),
            'width_in' => $this->width_in,
            'height_in' => $this->height_in,
            'price' => $this->price,
            'currency' => $this->currency,
            'description' => $this->description,
            'attributes' => (object) ($this->attributes ?? []),
        ];
    }
}
