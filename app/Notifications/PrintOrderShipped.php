<?php

namespace App\Notifications;

use App\Models\PrintOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PrintOrderShipped extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public PrintOrder $order) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("Your prints are on the way ({$this->order->number})")
            ->greeting('Your prints have shipped!');

        foreach ($this->order->tracking ?? [] as $t) {
            $mail->line(trim(($t['carrier'] ?? 'Carrier').': '.($t['number'] ?? '')));
            if (filled($t['url'] ?? null)) {
                $mail->action('Track package', $t['url']);
            }
        }

        return $mail->line('Thanks again for supporting my photography.');
    }
}
