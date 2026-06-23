<?php

namespace App\Notifications;

use App\Models\Loan;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoanDueSoonNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly Loan $loan) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $daysLeft = (int) now()->startOfDay()->diffInDays($this->loan->due_date->startOfDay());
        $dueLabel = $daysLeft === 1 ? 'tomorrow' : "in {$daysLeft} days";

        return (new MailMessage)
            ->subject('Library Book Due ' . ucfirst($dueLabel) . ' — ' . $this->loan->book->title)
            ->greeting('Hello, ' . $notifiable->name . '!')
            ->line("This is a reminder that the following book is due back {$dueLabel}:")
            ->line('**' . $this->loan->book->title . '** by ' . $this->loan->book->authors)
            ->line('**Due date:** ' . $this->loan->due_date->format('l, F j, Y'))
            ->line('Please return it to the library on time to avoid a fine.')
            ->salutation('ACDS Library');
    }
}
