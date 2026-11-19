<?php

use App\Enums\PrintProductType;
use App\Models\PrintProduct;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'prints.prodigi.key' => 'test-key',
        'prints.prodigi.environment' => 'sandbox',
        'prints.prodigi.shipping_method' => 'Standard',
        'prints.currency' => 'USD',
        'prints.shipping_flat' => 0,
        'prints.shipping_countries' => ['US', 'CA'],
    ]);
});

function product(string $sku, int $price, ?array $attributes = null, bool $active = false): PrintProduct
{
    return PrintProduct::create([
        'name' => 'Print', 'type' => PrintProductType::CANVAS, 'sku' => $sku, 'width_in' => 16, 'height_in' => 24,
        'price' => $price, 'currency' => 'USD', 'attributes' => $attributes, 'min_dpi' => 100, 'is_active' => $active, 'sort_order' => 0,
    ]);
}

/** Prodigi sandbox fakes: canvas (wrap options, ships to US), framed (US-only colours), fine art (ships to GB only). */
function fakeProdigi(array $quotes = []): void
{
    $products = [
        'GLOBAL-CAN-16X24' => ['attributes' => ['wrap' => ['Black', 'ImageWrap', 'MirrorWrap', 'White']],
            'variants' => [['attributes' => ['wrap' => 'ImageWrap'], 'shipsTo' => ['US', 'GB']], ['attributes' => ['wrap' => 'Black'], 'shipsTo' => ['US']]]],
        'GLOBAL-CFPM-16X24' => ['attributes' => ['color' => ['black', 'white', 'natural']],
            'variants' => [['attributes' => ['color' => 'black'], 'shipsTo' => ['US']]]],
        'GLOBAL-HPR-16X24' => ['attributes' => [],
            'variants' => [['attributes' => [], 'shipsTo' => ['GB']]]],
    ];

    Http::fake(function (Request $request) use ($products, $quotes) {
        if (preg_match('#/products/([^/?]+)$#', $request->url(), $m)) {
            $sku = rawurldecode($m[1]);

            return isset($products[$sku])
                ? Http::response(['outcome' => 'Ok', 'product' => ['sku' => $sku, ...$products[$sku]]])
                : Http::response(['outcome' => 'NotFound'], 404);
        }

        if (str_ends_with($request->url(), '/quotes')) {
            $sku = $request['items'][0]['sku'];
            [$items, $shipping] = $quotes[$sku] ?? ['20.00', '10.00'];

            return Http::response(['outcome' => 'Created', 'quotes' => [
                ['shipmentMethod' => 'Budget', 'costSummary' => ['items' => ['amount' => '1.00', 'currency' => 'USD'], 'shipping' => ['amount' => '1.00', 'currency' => 'USD']]],
                ['shipmentMethod' => 'Standard', 'costSummary' => ['items' => ['amount' => $items, 'currency' => 'USD'], 'shipping' => ['amount' => $shipping, 'currency' => 'USD']]],
            ]], 201);
        }

        return Http::response([], 500);
    });
}

it('fixes options, raises underpriced products and switches the good ones on', function () {
    fakeProdigi(['GLOBAL-CAN-16X24' => ['40.00', '12.00']]);
    $canvas = product('GLOBAL-CAN-16X24', 4900, ['wrap' => 'imagewrap', 'bogus' => 'x']);   // $52 cost: underpriced
    $framed = product('GLOBAL-CFPM-16X24', 19900);                                            // fine, missing colour
    $missing = product('GLOBAL-NOPE-1X1', 1000, active: true);
    $abroad = product('GLOBAL-HPR-16X24', 9900, active: true);

    $this->artisan('prints:setup', ['--activate' => true, '--force' => true])
        ->expectsOutputToContain('unknown SKU')
        ->expectsOutputToContain("doesn't ship to US")
        ->assertSuccessful();

    $canvas->refresh();
    expect($canvas->attributes)->toBe(['wrap' => 'ImageWrap'])
        // $52 cost at 40% minimum margin after Stripe → $93.49+ → next charm price $94
        ->and($canvas->price)->toBe(9400)
        ->and($canvas->is_active)->toBeTrue()
        ->and($framed->fresh()->attributes)->toBe(['color' => 'black'])
        ->and($framed->fresh()->price)->toBe(19900)
        ->and($framed->fresh()->is_active)->toBeTrue()
        ->and($missing->fresh()->is_active)->toBeFalse()
        ->and($abroad->fresh()->is_active)->toBeFalse();

    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/quotes')
        && $r['destinationCountryCode'] === 'US' && $r['shippingMethod'] === 'Standard'
        && $r['items'][0]['attributes'] === ['wrap' => 'ImageWrap']);
});

it('reprices everything from cost × markup on request', function () {
    fakeProdigi(['GLOBAL-CFPM-16X24' => ['30.00', '10.00']]);
    $framed = product('GLOBAL-CFPM-16X24', 19900, ['color' => 'black']);

    $this->artisan('prints:setup', ['--reprice' => true, '--markup' => 2.5, '--force' => true])->assertSuccessful();

    // $40 × 2.5 = $100 → charm $104
    expect($framed->fresh()->price)->toBe(10400)->and($framed->fresh()->is_active)->toBeFalse();
});

it('only prices goods when shipping is charged separately', function () {
    config(['prints.shipping_flat' => 1500]);
    fakeProdigi(['GLOBAL-CFPM-16X24' => ['30.00', '50.00']]);
    $framed = product('GLOBAL-CFPM-16X24', 1000, ['color' => 'black']);

    $this->artisan('prints:setup', ['--force' => true])->assertSuccessful();

    // Floor for a $30 cost at 40% margin → $53.66 → $54 (the $50 shipping is ignored)
    expect($framed->fresh()->price)->toBe(5400);
});

it('changes nothing on a dry run and refuses to run without a key', function () {
    fakeProdigi(['GLOBAL-CAN-16X24' => ['40.00', '12.00']]);
    $canvas = product('GLOBAL-CAN-16X24', 4900);

    $this->artisan('prints:setup', ['--dry-run' => true, '--activate' => true])->expectsOutputToContain('Dry run')->assertSuccessful();
    expect($canvas->fresh()->only('price', 'is_active', 'attributes'))->toBe(['price' => 4900, 'is_active' => false, 'attributes' => null]);

    config(['prints.prodigi.key' => null]);
    $this->artisan('prints:setup')->expectsOutputToContain('PRODIGI_API_KEY')->assertFailed();
});

it('creates the starter catalogue when there are no products', function () {
    fakeProdigi();

    $this->artisan('prints:setup', ['--only' => 'GLOBAL-CAN-16X24', '--dry-run' => true])->assertSuccessful();

    expect(PrintProduct::count())->toBe(27);
});
