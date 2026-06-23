<?php

namespace App\Console\Commands;

use App\Models\Loan;
use App\Notifications\LoanOverdueNotification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('library:send-overdue-notices')]
#[Description('Send overdue notices to members with loans past their due date')]
class SendOverdueNotices extends Command
{
    public function handle(): void
    {
        $loans = Loan::overdue()->with(['book', 'user'])->get();

        if ($loans->isEmpty()) {
            $this->info('No overdue loans found.');
            return;
        }

        foreach ($loans as $loan) {
            $loan->user->notify(new LoanOverdueNotification($loan));
        }

        $this->info("Sent {$loans->count()} overdue notice(s).");
    }
}
