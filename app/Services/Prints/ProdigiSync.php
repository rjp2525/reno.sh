<?php

declare(strict_types=1);

namespace App\Services\Prints;

use App\Enums\PrintOrderStatus;
use App\Models\PrintOrder;
use App\Notifications\PrintOrderShipped;
use Illuminate\Support\Facades\Notification;

/** Maps a Prodigi order payload onto our order (status, tracking). */
class ProdigiSync
{
    public function apply(PrintOrder $order, array $remote): PrintOrder
    {
        $stage = data_get($remote, 'status.stage');
        $shipments = collect(data_get($remote, 'shipments', []));

        $tracking = $shipments
            ->filter(fn ($s) => filled(data_get($s, 'tracking.number')) || filled(data_get($s, 'tracking.url')))
            ->map(fn ($s) => [
                'carrier' => data_get($s, 'carrier.name'),
                'number' => data_get($s, 'tracking.number'),
                'url' => data_get($s, 'tracking.url'),
            ])
            ->values()
            ->all();

        $issues = collect(data_get($remote, 'status.issues', []))->pluck('description')->filter()->implode(' ');

        $status = match (true) {
            $stage === 'Cancelled' => PrintOrderStatus::CANCELLED,
            $stage === 'Complete' && $tracking !== [] => PrintOrderStatus::SHIPPED,
            $stage === 'Complete' => PrintOrderStatus::COMPLETED,
            $tracking !== [] => PrintOrderStatus::SHIPPED,
            data_get($remote, 'status.details.printReadyAssetsPrepared') === 'Complete' => PrintOrderStatus::IN_PRODUCTION,
            default => $order->status === PrintOrderStatus::PAID ? PrintOrderStatus::SUBMITTED : $order->status,
        };

        $newlyShipped = $status === PrintOrderStatus::SHIPPED && $order->shipped_at === null;

        $order->update([
            'status' => $status,
            'tracking' => $tracking ?: $order->tracking,
            'error' => $issues ?: null,
            'shipped_at' => $newlyShipped ? now() : $order->shipped_at,
        ]);

        if ($newlyShipped && $order->email) {
            Notification::route('mail', $order->email)->notify(new PrintOrderShipped($order));
        }

        return $order;
    }
}
