<?php

namespace App\Http\Controllers\Prints;

use App\Http\Controllers\Controller;
use App\Http\Requests\PrintCheckoutRequest;
use App\Models\Photo;
use App\Models\PrintOrder;
use App\Models\PrintProduct;
use App\Services\Prints\PrintCheckout;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class PrintOrderController extends Controller
{
    /** Web: "Order print" button posts here, then we bounce to Stripe Checkout. */
    public function checkout(PrintCheckoutRequest $request, PrintCheckout $checkout): HttpResponse
    {
        [, $url] = $checkout->start(
            Photo::findOrFail($request->validated('photo_id')),
            PrintProduct::findOrFail($request->validated('product_id')),
            (int) $request->validated('quantity', 1),
            $request->validated('email'),
        );

        return Inertia::location($url);
    }

    /**
     * Post-checkout landing page. The order id is a ULID (unguessable) and we only
     * show what's needed to reassure the buyer: no address beyond city/country.
     */
    public function thanks(Request $request, PrintOrder $order): Response
    {
        $order->load('items.photo', 'items.product');

        return Inertia::render('prints/Thanks', [
            'order' => [
                'number' => $order->number,
                'status' => $order->status->value,
                'status_label' => $order->status->getLabel(),
                'total' => $order->formatMoney($order->total),
                'ship_to' => collect([$order->shipping_address['city'] ?? null, $order->shipping_address['country'] ?? null])->filter()->implode(', '),
                'tracking' => $order->tracking ?? [],
                'items' => $order->items->map(fn ($i) => [
                    'title' => $i->photo_title,
                    'product' => $i->product_name,
                    'quantity' => $i->quantity,
                    'thumbnail_url' => $i->photo?->thumbnail_url,
                    'photo_url' => $i->photo ? route('gallery.show', $i->photo) : null,
                ]),
            ],
        ]);
    }
}
