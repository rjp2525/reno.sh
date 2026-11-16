<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\PrintCheckoutRequest;
use App\Models\Photo;
use App\Models\PrintProduct;
use App\Services\Prints\PrintCheckout;
use Illuminate\Http\JsonResponse;

class PrintController extends Controller
{
    public function checkout(PrintCheckoutRequest $request, PrintCheckout $checkout): JsonResponse
    {
        [$order, $url] = $checkout->start(
            Photo::findOrFail($request->validated('photo_id')),
            PrintProduct::findOrFail($request->validated('product_id')),
            (int) ($request->validated('quantity') ?? 1),
            $request->validated('email'),
        );

        return response()->json(['data' => [
            'order_id' => (string) $order->id,
            'number' => $order->number,
            'checkout_url' => $url,
        ]], 201);
    }
}
