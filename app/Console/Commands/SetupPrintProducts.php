<?php

namespace App\Console\Commands;

use App\Models\PrintProduct;
use App\Services\Prints\ProdigiClient;
use Database\Seeders\PrintProductSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

/**
 * Prodigi's catalogue is fixed: you pick their SKUs and set your own retail prices.
 * This checks every print product against Prodigi, fixes its options, prices it from
 * what Prodigi actually charges you, and switches on the ones that are good to sell.
 */
class SetupPrintProducts extends Command
{
    protected $signature = 'prints:setup
        {--country= : Destination for costs and the ships-to check (default: first of PRINTS_SHIPPING_COUNTRIES)}
        {--markup=2.5 : Target retail price = Prodigi cost × this}
        {--min-margin=40 : Lowest margin (%) after Stripe fees; cheaper products are raised to it}
        {--reprice : Set every price from cost × markup, not only the ones below the minimum margin}
        {--activate : Switch on every product that checks out}
        {--only= : Only SKUs starting with this, e.g. GLOBAL-CAN}
        {--dry-run : Show the plan without saving anything}
        {--force : Save without asking (for non-interactive runs)}';

    protected $description = 'Verify print products against Prodigi, fix their options, price them from real costs and activate them';

    /** Preferred values when a Prodigi attribute has several options and none is set. */
    private const PREFERRED = [
        'wrap' => 'ImageWrap',
        'color' => 'black',
        'mountColor' => 'snow white',
        'glaze' => 'Acrylic / Perspex',
        'paperType' => 'HPR',
    ];

    /** Stripe's standard card fee: 2.9% + 30¢. */
    private const STRIPE_PERCENT = 0.029;

    private const STRIPE_FIXED = 30;

    public function handle(ProdigiClient $prodigi): int
    {
        if (! $prodigi->isConfigured()) {
            $this->components->error('Prodigi is not configured. Add PRODIGI_API_KEY (and PRODIGI_ENVIRONMENT=live for real orders) to .env.');

            return self::FAILURE;
        }

        if (PrintProduct::count() === 0) {
            $this->components->info('No print products yet: creating the starter catalogue (inactive).');
            $this->callSilently('db:seed', ['--class' => PrintProductSeeder::class, '--force' => true]);
        }

        $country = strtoupper((string) ($this->option('country') ?: Arr::first(config('prints.shipping_countries')) ?: 'US'));
        $currency = strtoupper((string) config('prints.currency'));
        $markup = max(1.0, (float) $this->option('markup'));
        $minMargin = min(95, max(0, (float) $this->option('min-margin'))) / 100;
        $shippingInPrice = (int) config('prints.shipping_flat') === 0;

        $this->components->twoColumnDetail('Prodigi environment', config('prints.prodigi.environment') === 'live' ? '<fg=green>live</>' : '<fg=yellow>sandbox</>');
        $this->components->twoColumnDetail('Costs for', "{$country} · {$currency} · ".config('prints.prodigi.shipping_method').' shipping'.($shippingInPrice ? ' (included in prices)' : ''));

        $products = PrintProduct::query()
            ->when($this->option('only'), fn ($q, $prefix) => $q->where('sku', 'like', $prefix.'%'))
            ->orderBy('sort_order')->get();

        $plans = $products->map(fn (PrintProduct $p) => $this->plan($p, $prodigi, $country, $currency, $markup, $minMargin, $shippingInPrice));

        $this->table(
            ['SKU', 'Size', 'Check', 'Options', 'Cost', 'Price', 'Margin', 'Active'],
            $plans->map(fn (array $plan) => [
                $plan['product']->sku,
                $plan['product']->sizeLabel(),
                $plan['status'] === 'ok' ? '<fg=green>ok</>' : "<fg=red>{$plan['status']}</>",
                $plan['attributesLabel'],
                $plan['cost'] !== null ? $this->money($plan['cost'], $currency) : '—',
                $this->priceLabel($plan, $currency),
                $plan['margin'] !== null ? $this->marginLabel($plan['margin'], $minMargin) : '—',
                $this->activeLabel($plan),
            ])->all(),
        );

        $changes = $plans->filter(fn (array $plan) => $plan['changes'] !== []);
        $this->summary($plans, $changes);

        if ($changes->isEmpty()) {
            $this->components->info('Nothing to change.');

            return $plans->contains(fn ($p) => $p['status'] !== 'ok') ? self::FAILURE : self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->components->info('Dry run: nothing saved.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Save changes to {$changes->count()} product(s)?", true)) {
            $this->components->warn('Nothing saved.');

            return self::SUCCESS;
        }

        $changes->each(fn (array $plan) => $plan['product']->update($plan['changes']));
        $this->components->info("Saved {$changes->count()} product(s).");

        return self::SUCCESS;
    }

    /** Work out what one product needs, without saving. */
    private function plan(PrintProduct $product, ProdigiClient $prodigi, string $country, string $currency, float $markup, float $minMargin, bool $shippingInPrice): array
    {
        $plan = ['product' => $product, 'status' => 'ok', 'attributesLabel' => '—', 'cost' => null, 'margin' => null, 'newPrice' => null, 'changes' => []];

        try {
            $remote = $prodigi->getProduct($product->sku);
        } catch (Throwable $e) {
            return [...$plan, 'status' => 'API error: '.Str::limit($e->getMessage(), 40)];
        }

        if (! $remote) {
            return $this->deactivate([...$plan, 'status' => 'unknown SKU']);
        }

        // Options: keep valid ones (with Prodigi's spelling), fill in missing ones, drop unknown ones.
        $attributes = $this->resolveAttributes((array) ($remote['attributes'] ?? []), (array) ($product->attributes ?? []));
        $plan['attributesLabel'] = $attributes ? collect($attributes)->map(fn ($v, $k) => "{$k}={$v}")->implode(', ') : 'none';
        if ($attributes !== (array) ($product->attributes ?? [])) {
            $plan['changes']['attributes'] = $attributes ?: null;
        }

        $variant = collect($remote['variants'] ?? [])->first(
            fn (array $v) => collect($v['attributes'] ?? [])->every(fn ($value, $key) => strcasecmp((string) ($attributes[$key] ?? ''), (string) $value) === 0),
        );

        if ($variant && isset($variant['shipsTo']) && ! in_array($country, $variant['shipsTo'], true)) {
            return $this->deactivate([...$plan, 'status' => "doesn't ship to {$country}"]);
        }

        try {
            $quote = $prodigi->quote([['sku' => $product->sku, 'attributes' => $attributes ?: null]], $country, $currency);
        } catch (Throwable $e) {
            return [...$plan, 'status' => 'quote failed: '.Str::limit($e->getMessage(), 40)];
        }

        if (! $quote) {
            return $this->deactivate([...$plan, 'status' => 'no quote for '.$country]);
        }

        if (strtoupper($quote['currency']) !== $currency) {
            return [...$plan, 'status' => "quoted in {$quote['currency']}, not {$currency}"];
        }

        $cost = (int) round(($quote['items'] + ($shippingInPrice ? $quote['shipping'] : 0)) * 100);
        $plan['cost'] = $cost;

        $price = (int) $product->price;
        $target = $this->charm((int) ceil($cost * $markup));
        $floor = $this->floorPrice($cost, $minMargin);

        $newPrice = match (true) {
            (bool) $this->option('reprice') => max($target, $floor),
            $this->margin($price, $cost) < $minMargin => $floor,
            default => $price,
        };

        if ($newPrice !== $price) {
            $plan['newPrice'] = $newPrice;
            $plan['changes']['price'] = $newPrice;
            $plan['changes']['currency'] = $currency;
        }

        $plan['margin'] = $this->margin($newPrice, $cost);

        if ($this->option('activate') && ! $product->is_active) {
            $plan['changes']['is_active'] = true;
        }

        return $plan;
    }

    private function deactivate(array $plan): array
    {
        if ($plan['product']->is_active) {
            $plan['changes']['is_active'] = false;
        }

        return $plan;
    }

    /**
     * @param  array<string, list<string>>  $options  Prodigi: attribute => allowed values
     * @param  array<string, string>  $current
     * @return array<string, string>
     */
    private function resolveAttributes(array $options, array $current): array
    {
        $resolved = [];

        foreach ($options as $name => $allowed) {
            $allowed = array_values((array) $allowed);
            if ($allowed === []) {
                continue;
            }

            $match = fn (?string $wanted) => $wanted === null ? null
                : Arr::first($allowed, fn ($v) => strcasecmp((string) $v, $wanted) === 0);

            $resolved[$name] = $match($current[$name] ?? null)
                ?? $match(self::PREFERRED[$name] ?? null)
                ?? $allowed[0];
        }

        ksort($resolved);

        return $resolved;
    }

    /** Share of the price left after Prodigi's cost and Stripe's fee. */
    private function margin(int $price, int $cost): float
    {
        if ($price <= 0) {
            return -1.0;
        }

        $fee = $price * self::STRIPE_PERCENT + self::STRIPE_FIXED;

        return ($price - $fee - $cost) / $price;
    }

    /** The lowest "charm" price that still clears the minimum margin. */
    private function floorPrice(int $cost, float $minMargin): int
    {
        $exact = (int) ceil(($cost + self::STRIPE_FIXED) / max(0.05, 1 - self::STRIPE_PERCENT - $minMargin));

        return $this->charm($exact);
    }

    /** Round up to the next $5 step minus $1 ($34, $39, $44…), same as the starter catalogue. */
    private function charm(int $cents): int
    {
        return (int) (ceil(($cents + 100) / 500) * 500 - 100);
    }

    private function summary(Collection $plans, Collection $changes): void
    {
        $this->newLine();
        $problems = $plans->filter(fn ($p) => $p['status'] !== 'ok');
        $this->components->twoColumnDetail('Checked', (string) $plans->count());
        $this->components->twoColumnDetail('Problems', $problems->isEmpty() ? '<fg=green>0</>' : '<fg=red>'.$problems->count().'</>');
        $this->components->twoColumnDetail('Price changes', (string) $plans->whereNotNull('newPrice')->count());
        $this->components->twoColumnDetail('Option fixes', (string) $changes->filter(fn ($p) => array_key_exists('attributes', $p['changes']))->count());
        $this->components->twoColumnDetail('Will be active', (string) $plans->filter(fn ($p) => $p['changes']['is_active'] ?? $p['product']->is_active)->count());

        if (! $this->option('activate') && $plans->contains(fn ($p) => $p['status'] === 'ok' && ! $p['product']->is_active)) {
            $this->components->info('Products stay off until you run this with --activate (or switch them on in /admin/print-products).');
        }
    }

    private function priceLabel(array $plan, string $currency): string
    {
        $old = $this->money((int) $plan['product']->price, $currency);

        return $plan['newPrice'] !== null ? "{$old} → <fg=cyan>".$this->money($plan['newPrice'], $currency).'</>' : $old;
    }

    private function marginLabel(float $margin, float $min): string
    {
        $pct = round($margin * 100).'%';

        return $margin < $min ? "<fg=red>{$pct}</>" : $pct;
    }

    private function activeLabel(array $plan): string
    {
        $now = (bool) $plan['product']->is_active;
        $next = $plan['changes']['is_active'] ?? $now;

        return match (true) {
            $next && ! $now => '<fg=green>off → on</>',
            ! $next && $now => '<fg=red>on → off</>',
            default => $now ? 'on' : 'off',
        };
    }

    private function money(int $cents, string $currency): string
    {
        return ($currency === 'USD' ? '$' : $currency.' ').number_format($cents / 100, 2);
    }
}
