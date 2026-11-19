<?php

declare(strict_types=1);

namespace App\Services\Prints;

use App\Models\PrintOrder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin client for Prodigi Print API v4.
 *
 * @see https://www.prodigi.com/print-api/docs/reference/
 */
class ProdigiClient
{
    public function isConfigured(): bool
    {
        return filled(config('prints.prodigi.key'));
    }

    public function createOrder(PrintOrder $order): array
    {
        $order->loadMissing('items.photo');
        $address = $order->shipping_address ?? [];

        $payload = [
            'merchantReference' => $order->number,
            'idempotencyKey' => (string) $order->id,
            'shippingMethod' => config('prints.prodigi.shipping_method'),
            'callbackUrl' => route('webhooks.prodigi', ['token' => config('prints.prodigi.callback_token')]),
            'recipient' => array_filter([
                'name' => $order->name ?: $address['name'] ?? 'Customer',
                'email' => $order->email,
                'phoneNumber' => $order->phone,
                'address' => array_filter([
                    'line1' => $address['line1'] ?? null,
                    'line2' => $address['line2'] ?? null,
                    'postalOrZipCode' => $address['postal_code'] ?? null,
                    'countryCode' => $address['country'] ?? null,
                    'townOrCity' => $address['city'] ?? null,
                    'stateOrCounty' => $address['state'] ?? null,
                ]),
            ]),
            'items' => $order->items->map(function ($item) {
                if (! $item->photo) {
                    throw new RuntimeException("Order item {$item->id} has no photo to print.");
                }

                return array_filter([
                    'merchantReference' => (string) $item->id,
                    'sku' => $item->sku,
                    'copies' => $item->quantity,
                    'sizing' => 'fillPrintArea',
                    'attributes' => $item->attributes ?: null,
                    'assets' => [['printArea' => 'default', 'url' => $item->photo->printAssetUrl()]],
                ]);
            })->values()->all(),
            'metadata' => ['order_id' => (string) $order->id],
        ];

        return $this->request()->post('/Orders', $payload)->throw()->json('order');
    }

    public function getOrder(string $prodigiOrderId): array
    {
        return $this->request()->get('/Orders/'.$prodigiOrderId)->throw()->json('order');
    }

    /** Product details (print areas, attributes, sizes) for verifying catalog SKUs. */
    public function getProduct(string $sku): ?array
    {
        try {
            return $this->request()->get('/products/'.rawurlencode($sku))->throw()->json('product');
        } catch (RequestException $e) {
            if ($e->response->status() === 404) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * What Prodigi charges you for one copy of each SKU shipped to a country.
     *
     * @param  list<array{sku: string, attributes?: array<string, string>|null}>  $items
     * @return array{items: float, shipping: float, currency: string, method: string}|null null when Prodigi can't ship it there
     */
    public function quote(array $items, string $country, ?string $currency = null, ?string $method = null): ?array
    {
        $method ??= config('prints.prodigi.shipping_method');

        $response = $this->request()->post('/quotes', [
            'shippingMethod' => $method,
            'destinationCountryCode' => strtoupper($country),
            'currencyCode' => strtoupper($currency ?? config('prints.currency')),
            'items' => array_map(fn (array $item) => array_filter([
                'sku' => $item['sku'],
                'copies' => 1,
                'attributes' => $item['attributes'] ?? null,
                'assets' => [['printArea' => 'default']],
            ]), $items),
        ]);

        if ($response->clientError()) {
            return null;
        }

        $quote = collect($response->throw()->json('quotes', []))
            ->first(fn (array $q) => strcasecmp($q['shipmentMethod'] ?? '', $method) === 0);

        if (! $quote) {
            return null;
        }

        return [
            'items' => (float) data_get($quote, 'costSummary.items.amount', 0),
            'shipping' => (float) data_get($quote, 'costSummary.shipping.amount', 0),
            'currency' => (string) data_get($quote, 'costSummary.items.currency', $currency ?? config('prints.currency')),
            'method' => (string) $quote['shipmentMethod'],
        ];
    }

    protected function request(): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Prodigi is not configured (PRODIGI_API_KEY).');
        }

        $env = config('prints.prodigi.environment') === 'live' ? 'live' : 'sandbox';

        return Http::baseUrl(config("prints.prodigi.base_urls.$env"))
            ->withHeaders(['X-API-Key' => config('prints.prodigi.key')])
            ->acceptJson()
            ->asJson()
            ->timeout(30)
            ->retry(3, 500, fn ($e) => $e instanceof ConnectionException
                || ($e instanceof RequestException && $e->response->serverError()), throw: false);
    }
}
