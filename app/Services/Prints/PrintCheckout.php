<?php

declare(strict_types=1);

namespace App\Services\Prints;

use App\Enums\PrintOrderStatus;
use App\Jobs\SubmitPrintOrder;
use App\Models\Photo;
use App\Models\PrintOrder;
use App\Models\PrintProduct;
use App\Notifications\PrintOrderPaid;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Stripe\Checkout\Session;
use Stripe\StripeClient;

class PrintCheckout
{
    public function __construct(private readonly ?StripeClient $stripe = null) {}

    public function stripe(): StripeClient
    {
        return $this->stripe ?? new StripeClient(['api_key' => (string) config('prints.stripe.secret')]);
    }

    /**
     * Create a pending order and its Stripe Checkout session.
     *
     * @return array{0: PrintOrder, 1: string} order + checkout URL
     */
    public function start(Photo $photo, PrintProduct $product, int $quantity = 1, ?string $email = null, ?string $cancelUrl = null): array
    {
        if (blank(config('prints.stripe.secret'))) {
            throw ValidationException::withMessages(['product_id' => 'Print ordering is not available right now.']);
        }

        if (! $photo->is_published || ! $photo->prints_enabled || ! $product->is_active || ! $product->fits($photo->width, $photo->height)) {
            throw ValidationException::withMessages(['product_id' => 'That print option is not available for this photo.']);
        }

        $currency = strtoupper($product->currency ?: config('prints.currency'));
        $shipping = (int) config('prints.shipping_flat');

        $order = DB::transaction(function () use ($photo, $product, $quantity, $email, $currency, $shipping) {
            $order = PrintOrder::create([
                'status' => PrintOrderStatus::PENDING_PAYMENT,
                'email' => $email,
                'currency' => $currency,
                'subtotal' => $product->price * $quantity,
                'shipping' => $shipping,
                'total' => $product->price * $quantity + $shipping,
            ]);

            $order->items()->create([
                'photo_id' => $photo->id,
                'print_product_id' => $product->id,
                'photo_title' => $photo->title,
                'product_name' => $product->name.' · '.$product->sizeLabel(),
                'sku' => $product->sku,
                'attributes' => $product->attributes,
                'quantity' => $quantity,
                'unit_price' => $product->price,
            ]);

            return $order;
        });

        $session = $this->stripe()->checkout->sessions->create(array_filter([
            'mode' => 'payment',
            'client_reference_id' => (string) $order->id,
            'customer_email' => $email,
            'metadata' => ['print_order_id' => (string) $order->id, 'number' => $order->number],
            'payment_intent_data' => ['metadata' => ['print_order_id' => (string) $order->id]],
            'line_items' => [[
                'quantity' => $quantity,
                'price_data' => [
                    'currency' => strtolower($currency),
                    'unit_amount' => $product->price,
                    'product_data' => array_filter([
                        'name' => "{$photo->title}: {$product->name} ({$product->sizeLabel()})",
                        'description' => $product->description,
                        'images' => $photo->thumbnail_url ? [$photo->thumbnail_url] : null,
                    ]),
                ],
            ]],
            'shipping_address_collection' => ['allowed_countries' => array_values(config('prints.shipping_countries'))],
            'shipping_options' => [[
                'shipping_rate_data' => [
                    'display_name' => config('prints.shipping_label'),
                    'type' => 'fixed_amount',
                    'fixed_amount' => ['amount' => $shipping, 'currency' => strtolower($currency)],
                    'delivery_estimate' => [
                        'minimum' => ['unit' => 'business_day', 'value' => config('prints.shipping_days.0')],
                        'maximum' => ['unit' => 'business_day', 'value' => config('prints.shipping_days.1')],
                    ],
                ],
            ]],
            'phone_number_collection' => ['enabled' => true],
            'automatic_tax' => config('prints.stripe.automatic_tax') ? ['enabled' => true] : null,
            'success_url' => route('prints.thanks', $order).'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $cancelUrl ?? route('gallery.show', $photo),
            'expires_at' => now()->addHours(2)->timestamp,
        ]));

        $order->update(['stripe_checkout_session_id' => $session->id]);

        return [$order, (string) $session->url];
    }

    /** checkout.session.completed / async_payment_succeeded. Idempotent. */
    public function markPaid(Session $session): ?PrintOrder
    {
        if ($session->payment_status !== 'paid') {
            return null;
        }

        [$order, $changed] = DB::transaction(function () use ($session) {
            $order = $this->orderFor($session);
            if (! $order || $order->status !== PrintOrderStatus::PENDING_PAYMENT) {
                return [$order, false];
            }

            $this->applyPayment($order, $session);

            return [$order, true];
        });

        if (! $changed) {
            return $order;
        }

        SubmitPrintOrder::dispatch($order);

        Notification::route('mail', config('prints.notify'))->notify(new PrintOrderPaid($order, forOwner: true));
        if ($order->email) {
            Notification::route('mail', $order->email)->notify(new PrintOrderPaid($order));
        }

        return $order;
    }

    private function applyPayment(PrintOrder $order, Session $session): void
    {
        $customer = $session->customer_details;
        $ship = $session->collected_information?->shipping_details ?? ($session->shipping_details ?? null);
        $addr = $ship?->address;

        $order->update([
            'status' => PrintOrderStatus::PAID,
            'email' => $customer?->email ?? $order->email,
            'name' => $ship?->name ?? $customer?->name,
            'phone' => $customer?->phone,
            'shipping_address' => $addr ? [
                'name' => $ship->name ?? null,
                'line1' => $addr->line1,
                'line2' => $addr->line2,
                'city' => $addr->city,
                'state' => $addr->state,
                'postal_code' => $addr->postal_code,
                'country' => $addr->country,
            ] : null,
            'subtotal' => (int) $session->amount_subtotal,
            'shipping' => (int) ($session->total_details?->amount_shipping ?? $order->shipping),
            'tax' => (int) ($session->total_details?->amount_tax ?? 0),
            'total' => (int) $session->amount_total,
            'stripe_payment_intent_id' => is_string($session->payment_intent) ? $session->payment_intent : $session->payment_intent?->id,
            'paid_at' => now(),
        ]);
    }

    public function markExpired(Session $session): void
    {
        DB::transaction(function () use ($session) {
            $order = $this->orderFor($session);

            if ($order?->status === PrintOrderStatus::PENDING_PAYMENT) {
                $order->update(['status' => PrintOrderStatus::CANCELLED]);
            }
        });
    }

    private function orderFor(Session $session): ?PrintOrder
    {
        return PrintOrder::query()
            ->where('stripe_checkout_session_id', $session->id)
            ->when($session->client_reference_id, fn ($q, $id) => $q->orWhere('id', $id))
            ->lockForUpdate()
            ->first();
    }
}
