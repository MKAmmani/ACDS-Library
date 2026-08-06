<?php

namespace Database\Seeders;

use App\Models\Event;
use Illuminate\Database\Seeder;

class EventSeeder extends Seeder
{
    public function run(): void
    {
        $events = [
            [
                'title'      => 'Annual Democracy Lecture 2026',
                'starts_at'  => '2026-08-14 10:00:00',
                'time_label' => '10:00 AM',
                'place'      => 'Mambayya House Auditorium',
            ],
            [
                'title'      => 'Library orientation for new students',
                'starts_at'  => '2026-08-22 11:00:00',
                'time_label' => '11:00 AM',
                'place'      => 'Main Reading Hall',
            ],
            [
                'title'      => 'Workshop: research & citation tools',
                'starts_at'  => '2026-09-05 14:00:00',
                'time_label' => '2:00 PM',
                'place'      => 'ICT Training Centre',
            ],
            [
                'title'      => 'Book donation drive — closing day',
                'starts_at'  => '2026-09-19 09:00:00',
                'time_label' => 'All day',
                'place'      => 'Circulation Desk',
            ],
        ];

        foreach ($events as $event) {
            Event::firstOrCreate(['title' => $event['title']], $event);
        }
    }
}
