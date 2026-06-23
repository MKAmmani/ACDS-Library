<?php

namespace App\Console\Commands;

use App\Models\Loan;
use App\Notifications\LoanDueSoonNotification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('library:send-due-reminders {--days=3 : Send reminders for loans due within this many days}')]
#[Description('Send email reminders to members whose loans are due soon')]
class SendDueDateReminders extends Command
{
    public function handle(): void
    {
        $days = (int) $this->option('days');

        $loans = Loan::active()
            ->with(['book', 'user'])
            ->whereDate('due_date', now()->addDays($days)->toDateString())
            ->get();

        if ($loans->isEmpty()) {
            $this->info("No loans due in {$days} day(s).");
            return;
        }

        foreach ($loans as $loan) {
            $loan->user->notify(new LoanDueSoonNotification($loan));
        }

        $this->info("Sent {$loans->count()} due-date reminder(s).");
    }
}
