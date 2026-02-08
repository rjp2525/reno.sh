<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContactFormNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $subject,
        public readonly string $contactMessage,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Contact Form: {$this->subject}")
            ->replyTo($this->email, $this->name)
            ->view('mail.contact-form', [
                'name' => $this->name,
                'email' => $this->email,
                'contactSubject' => $this->subject,
                'contactMessage' => $this->contactMessage,
            ]);
    }
}
