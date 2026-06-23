<?php

use Illuminate\Support\Facades\Schedule;

// Send reminders for loans due in 3 days — runs every morning at 08:00
Schedule::command('library:send-due-reminders --days=3')->dailyAt('08:00');

// Send overdue notices — runs every morning at 09:00
Schedule::command('library:send-overdue-notices')->dailyAt('09:00');
