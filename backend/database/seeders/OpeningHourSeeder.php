<?php

namespace Database\Seeders;

use App\Models\OpeningHour;
use Illuminate\Database\Seeder;

class OpeningHourSeeder extends Seeder
{
    public function run(): void
    {
        $hours = [
            ['day_label' => 'Monday – Friday', 'time_label' => '8:00 – 17:00', 'is_closed' => false, 'sort_order' => 1],
            ['day_label' => 'Saturday',        'time_label' => '9:00 – 14:00', 'is_closed' => false, 'sort_order' => 2],
            ['day_label' => 'Sunday',          'time_label' => 'Closed',       'is_closed' => true,  'sort_order' => 3],
        ];

        foreach ($hours as $hour) {
            OpeningHour::firstOrCreate(['day_label' => $hour['day_label']], $hour);
        }
    }
}
