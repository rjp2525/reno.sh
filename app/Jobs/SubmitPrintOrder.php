<?php

namespace App\Jobs;

use App\Enums\PrintOrderStatus;
use App\Models\PrintOrder;
use App\Services\Prints\ProdigiClient;
use App\Services\Prints\ProdigiSync;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Send a paid order to Prodigi. Safe to retry: Prodigi dedupes on idempotencyKey (our order id). */
class SubmitPrintOrder implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public array $backoff = [30, 120, 600, 1800];

    public function __construct(public PrintOrder $order)
    {
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return (string) $this->order->id;
    }

    public function handle(ProdigiClient $prodigi, ProdigiSync $sync): void
    {
        $order = $this->order->fresh();

        if (! $order || $order->prodigi_order_id || ! in_array($order->status, [PrintOrderStatus::PAID, PrintOrderStatus::FAILED], true)) {
            return;
        }

        $remote = $prodigi->createOrder($order);

        $order->update([
            'prodigi_order_id' => $remote['id'] ?? null,
            'status' => PrintOrderStatus::SUBMITTED,
            'submitted_at' => now(),
            'error' => null,
        ]);

        $sync->apply($order, $remote);
    }

    public function failed(Throwable $e): void
    {
        Log::error('Prodigi order submission failed', ['order' => $this->order->id, 'error' => $e->getMessage()]);

        $this->order->update(['status' => PrintOrderStatus::FAILED, 'error' => $e->getMessage()]);
    }
}
