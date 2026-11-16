<?php

use App\Enums\PhotoProcessingStatus;
use App\Enums\PrintOrderStatus;
use App\Enums\PrintProductType;
use App\Jobs\SubmitPrintOrder;
use App\Models\Photo;
use App\Models\PrintOrder;
use App\Models\PrintProduct;
use App\Notifications\PrintOrderPaid;
use App\Notifications\PrintOrderShipped;
use App\Services\Prints\ProdigiClient;
use App\Services\Prints\ProdigiSync;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Stripe\StripeClient;

/** Records Stripe API calls and answers with canned JSON. */
class FakeStripeHttp implements ClientInterface
{
    public array $requests = [];

    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
    {
        $this->requests[] = compact('method', 'absUrl', 'params');

        return [json_encode([
            'id' => 'cs_test_123',
            'object' => 'checkout.session',
            'url' => 'https://checkout.stripe.com/c/pay/cs_test_123',
            'payment_status' => 'unpaid',
        ]), 200, []];
    }
}

function stripeSignature(string $payload, string $secret): string
{
    $t = time();

    return "t={$t},v1=".hash_hmac('sha256', "{$t}.{$payload}", $secret);
}

beforeEach(function () {
    config([
        'prints.stripe.secret' => 'sk_test_x',
        'prints.stripe.webhook_secret' => 'whsec_test',
        'prints.prodigi.key' => 'prodigi_test',
        'prints.prodigi.callback_token' => 'cb-token',
        'prints.notify' => 'owner@example.com',
    ]);

    $this->app->forgetInstance(StripeClient::class);
    ApiRequestor::setHttpClient($this->stripeHttp = new FakeStripeHttp);

    Storage::fake('photos_originals');
    Storage::disk('photos_originals')->put('uploads/sunrise.jpg', 'jpeg-bytes');

    // 6000×4000 (3:2) is enough for a 12×18 at 150dpi (2700px long edge) but not 30×45.
    $this->photo = Photo::factory()->create([
        'title' => 'Sunrise',
        'original_path' => 'uploads/sunrise.jpg',
        'width' => 6000,
        'height' => 4000,
        'is_published' => true,
        'prints_enabled' => true,
        'processing_status' => PhotoProcessingStatus::COMPLETED,
    ]);

    $make = fn (float $w, float $h, int $price, array $extra = []) => PrintProduct::create([
        'name' => 'Fine Art Print', 'type' => PrintProductType::FINE_ART, 'sku' => "GLOBAL-HPR-{$w}X{$h}",
        'width_in' => $w, 'height_in' => $h, 'price' => $price, 'currency' => 'USD', 'min_dpi' => 150, ...$extra,
    ]);

    $this->fits = $make(12, 18, 6500);
    $this->tooBig = $make(30, 45, 30000);
    $this->wrongRatio = $make(16, 20, 9000);
    $this->inactive = $make(8, 12, 4500, ['is_active' => false]);
});

afterEach(fn () => ApiRequestor::setHttpClient(null));

it('only offers sizes that match the photo aspect ratio and resolution', function () {
    expect($this->photo->printOptions()->pluck('id')->all())->toBe([$this->fits->id]);

    $this->getJson("/api/v1/photos/{$this->photo->slug}")
        ->assertOk()
        ->assertJsonCount(1, 'data.print_options')
        ->assertJsonPath('data.print_options.0.size_label', '12×18 in')
        ->assertJsonPath('data.print_options.0.price', 6500);
});

it('starts a Stripe checkout from the API', function () {
    $response = $this->postJson('/api/v1/prints/checkout', [
        'photo_id' => $this->photo->id,
        'product_id' => $this->fits->id,
        'quantity' => 2,
    ])->assertCreated()->assertJsonPath('data.checkout_url', 'https://checkout.stripe.com/c/pay/cs_test_123');

    $order = PrintOrder::findOrFail($response->json('data.order_id'));
    expect($order->status)->toBe(PrintOrderStatus::PENDING_PAYMENT)
        ->and($order->subtotal)->toBe(13000)
        ->and($order->stripe_checkout_session_id)->toBe('cs_test_123')
        ->and($order->items->first()->sku)->toBe('GLOBAL-HPR-12X18');

    $params = $this->stripeHttp->requests[0]['params'];
    expect($params['line_items'][0]['price_data']['unit_amount'])->toBe(6500)
        ->and($params['line_items'][0]['quantity'])->toBe(2)
        ->and($params['client_reference_id'])->toBe($order->id);
});

it('refuses sizes the photo cannot be printed at', function (string $product) {
    $this->postJson('/api/v1/prints/checkout', ['photo_id' => $this->photo->id, 'product_id' => $this->{$product}->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('product_id');

    expect(PrintOrder::count())->toBe(0);
})->with(['tooBig', 'wrongRatio', 'inactive']);

it('redirects web checkout to Stripe', function () {
    $this->post('/prints/checkout', ['photo_id' => $this->photo->id, 'product_id' => $this->fits->id], ['X-Inertia' => 'true'])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', 'https://checkout.stripe.com/c/pay/cs_test_123');
});

it('rejects unsigned Stripe webhooks', function () {
    $this->postJson('/webhooks/stripe', ['type' => 'checkout.session.completed'])->assertStatus(400);
});

it('marks the order paid from a signed webhook and submits it to Prodigi', function () {
    Queue::fake();
    Notification::fake();

    $this->postJson('/api/v1/prints/checkout', ['photo_id' => $this->photo->id, 'product_id' => $this->fits->id])->assertCreated();
    $order = PrintOrder::first();

    $payload = json_encode([
        'id' => 'evt_1', 'object' => 'event', 'type' => 'checkout.session.completed',
        'data' => ['object' => [
            'id' => 'cs_test_123', 'object' => 'checkout.session', 'client_reference_id' => $order->id,
            'payment_status' => 'paid', 'amount_subtotal' => 6500, 'amount_total' => 7200,
            'total_details' => ['amount_shipping' => 0, 'amount_tax' => 700], 'payment_intent' => 'pi_1',
            'customer_details' => ['email' => 'buyer@example.com', 'name' => 'Ada Buyer', 'phone' => '+15555550100'],
            'collected_information' => ['shipping_details' => ['name' => 'Ada Buyer', 'address' => [
                'line1' => '1 Main St', 'line2' => null, 'city' => 'Denver', 'state' => 'CO', 'postal_code' => '80202', 'country' => 'US',
            ]]],
        ]],
    ]);

    $send = fn () => $this->call('POST', '/webhooks/stripe', [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => stripeSignature($payload, 'whsec_test'), 'CONTENT_TYPE' => 'application/json',
    ], $payload)->assertOk();

    $send();
    $send(); // Stripe retries: must stay idempotent.

    $order->refresh();
    expect($order->status)->toBe(PrintOrderStatus::PAID)
        ->and($order->total)->toBe(7200)
        ->and($order->tax)->toBe(700)
        ->and($order->shipping_address['city'])->toBe('Denver')
        ->and($order->email)->toBe('buyer@example.com');

    Queue::assertPushed(SubmitPrintOrder::class, 1);
    Notification::assertSentOnDemandTimes(PrintOrderPaid::class, 2);
});

it('submits paid orders to Prodigi with a signed original URL', function () {
    Http::fake(['api.sandbox.prodigi.com/*' => Http::response(['outcome' => 'Created', 'order' => [
        'id' => 'ord_123', 'status' => ['stage' => 'InProgress', 'issues' => [], 'details' => []], 'shipments' => [],
    ]], 200)]);

    $order = PrintOrder::create(['status' => PrintOrderStatus::PAID, 'currency' => 'USD', 'email' => 'buyer@example.com', 'name' => 'Ada',
        'shipping_address' => ['line1' => '1 Main St', 'city' => 'Denver', 'state' => 'CO', 'postal_code' => '80202', 'country' => 'US']]);
    $order->items()->create(['photo_id' => $this->photo->id, 'print_product_id' => $this->fits->id, 'photo_title' => 'Sunrise',
        'product_name' => 'Fine Art Print', 'sku' => 'GLOBAL-HPR-12X18', 'quantity' => 1, 'unit_price' => 6500]);

    (new SubmitPrintOrder($order))->handle(app(ProdigiClient::class), app(ProdigiSync::class));

    expect($order->fresh())->prodigi_order_id->toBe('ord_123')->status->toBe(PrintOrderStatus::SUBMITTED);

    Http::assertSent(function ($request) use ($order) {
        $item = $request['items'][0];

        return $request->hasHeader('X-API-Key', 'prodigi_test')
            && $request['idempotencyKey'] === (string) $order->id
            && $request['recipient']['address']['townOrCity'] === 'Denver'
            && $item['sku'] === 'GLOBAL-HPR-12X18'
            && str_contains($item['assets'][0]['url'], 'uploads/sunrise.jpg')
            && str_contains($item['assets'][0]['url'], 'expiration=')
            && str_contains($request['callbackUrl'], 'token=cb-token');
    });
});

it('only trusts Prodigi callbacks with the token, and re-fetches the order', function () {
    Notification::fake();
    $order = PrintOrder::create(['status' => PrintOrderStatus::SUBMITTED, 'currency' => 'USD', 'email' => 'buyer@example.com', 'prodigi_order_id' => 'ord_123']);

    Http::fake(['api.sandbox.prodigi.com/v4.0/Orders/ord_123' => Http::response(['order' => [
        'id' => 'ord_123', 'status' => ['stage' => 'Complete', 'issues' => []],
        'shipments' => [['carrier' => ['name' => 'UPS'], 'tracking' => ['number' => '1Z999', 'url' => 'https://ups.example/1Z999']]],
    ]])]);

    // Spoofed body without the token: ignored.
    $this->postJson('/webhooks/prodigi', ['data' => ['order' => ['id' => 'ord_123']]])->assertNotFound();
    expect($order->fresh()->status)->toBe(PrintOrderStatus::SUBMITTED);

    // Real callback: body says nothing useful, the API fetch is the source of truth.
    $this->postJson('/webhooks/prodigi?token=cb-token', ['data' => ['order' => ['id' => 'ord_123', 'status' => ['stage' => 'Cancelled']]]])->assertOk();

    $order->refresh();
    expect($order->status)->toBe(PrintOrderStatus::SHIPPED)
        ->and($order->tracking[0]['number'])->toBe('1Z999')
        ->and($order->shipped_at)->not->toBeNull();

    Notification::assertSentOnDemand(PrintOrderShipped::class);
});

it('shows the order status page', function () {
    $order = PrintOrder::create(['status' => PrintOrderStatus::PAID, 'currency' => 'USD', 'total' => 6500,
        'shipping_address' => ['city' => 'Denver', 'country' => 'US', 'line1' => 'SECRET STREET']]);

    $this->get("/prints/orders/{$order->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('prints/Thanks')
            ->where('order.number', $order->number)
            ->where('order.ship_to', 'Denver, US'))
        ->assertDontSee('SECRET STREET');
});

it('signs original URLs on the real originals disk and rejects tampering', function () {
    Storage::forgetDisk('photos_originals');
    $disk = Storage::disk('photos_originals');
    $disk->put('uploads/sunrise.jpg', 'jpeg-bytes');

    $url = $this->photo->printAssetUrl();
    expect($url)->toContain('/originals/uploads/sunrise.jpg')->toContain('signature=');

    $path = parse_url($url, PHP_URL_PATH).'?'.parse_url($url, PHP_URL_QUERY);
    $this->get($path)->assertOk();
    $this->get(str_replace('signature=', 'signature=x', $path))->assertForbidden();
    $this->get('/originals/uploads/sunrise.jpg')->assertForbidden();

    $disk->delete('uploads/sunrise.jpg');
});
