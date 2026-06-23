<?php

namespace App\Notifications;

use App\Models\Loan;
use App\Models\LoanPolicy;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoanOverdueNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly Loan $loan) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $daysOverdue = (int) $this->loan->due_date->startOfDay()->diffInDays(now()->startOfDay());
        $policy      = LoanPolicy::forUser($notifiable);
        $accruedFine = min(
            round($daysOverdue * $policy->fine_per_day, 2),
            $policy->max_fine ?? PHP_INT_MAX
        );

        $message = (new MailMessage)
            ->subject('OVERDUE: Please Return — ' . $this->loan->book->title)
            ->greeting('Hello, ' . $notifiable->name . '!')
            ->line('The following book is **' . $daysOverdue . ' day(s) overdue**:')
            ->line('**' . $this->loan->book->title . '** by ' . $this->loan->book->authors)
            ->line('**Was due:** ' . $this->loan->due_date->format('l, F j, Y'));

        if ($accruedFine > 0) {
            $message->line('**Accrued fine so far:** $' . number_format($accruedFine, 2));
        }

        return $message
            ->line('Please return the book to the library as soon as possible.')
            ->salutation('ACDS Library');
    }
}
