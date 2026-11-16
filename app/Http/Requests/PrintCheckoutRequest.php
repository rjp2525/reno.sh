<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PrintCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'photo_id' => ['required', 'string', 'exists:photos,id'],
            'product_id' => ['required', 'string', 'exists:print_products,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:10'],
            'email' => ['nullable', 'email', 'max:255'],
        ];
    }
}
