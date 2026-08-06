<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountCreatedNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly User $account, public readonly string $password) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your ACDS Library Account Has Been Created')
            ->greeting('Hello, ' . $this->account->name . '!')
            ->line('An account has been created for you on the ACDS Library system.')
            ->line('**Email:** ' . $this->account->email)
            ->line('**Temporary Password:** ' . $this->password)
            ->line('Please sign in and change your password as soon as possible.')
            ->salutation('ACDS Library');
    }
}
