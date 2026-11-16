<?php

namespace App\Notifications;

use App\Models\PrintOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PrintOrderPaid extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public PrintOrder $order, public bool $forOwner = false) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = $this->order->loadMissing('items');
        $mail = (new MailMessage)
            ->subject($this->forOwner ? "New print order {$order->number}" : "Your print order {$order->number}")
            ->greeting($this->forOwner ? 'New print order 🎉' : 'Thanks for your order!');

        if (! $this->forOwner) {
            $mail->line("Your prints are being prepared by our fine-art lab. You'll get another email with tracking once they ship.");
        }

        foreach ($order->items as $item) {
            $mail->line("• {$item->quantity} × {$item->photo_title}, {$item->product_name}");
        }

        $mail->line('Total: '.$order->formatMoney($order->total));

        return $this->forOwner
            ? $mail->action('View order', url("/admin/print-orders/{$order->id}"))
            : $mail->action('Order status', route('prints.thanks', $order));
    }
}
