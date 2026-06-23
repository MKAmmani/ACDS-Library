<?php

namespace App\Notifications;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReservationReadyNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly Reservation $reservation) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $expiresAt = $this->reservation->expires_at?->format('l, F j, Y');

        return (new MailMessage)
            ->subject('Your Reserved Book is Ready — ' . $this->reservation->book->title)
            ->greeting('Hello, ' . $notifiable->name . '!')
            ->line('Great news! A copy of your reserved book is now available:')
            ->line('**' . $this->reservation->book->title . '** by ' . $this->reservation->book->authors)
            ->when($expiresAt, fn ($m) => $m->line("**Please collect it by:** {$expiresAt}"))
            ->line('If you do not collect it by this date, your reservation will expire and the next person in the queue will be notified.')
            ->salutation('ACDS Library');
    }
}
