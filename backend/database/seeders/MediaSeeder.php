<?php

namespace Database\Seeders;

use App\Models\Media;
use Illuminate\Database\Seeder;

class MediaSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            [
                'title'          => 'Annual Democracy Lecture: Federalism and the Future of Nigeria',
                'kind'           => 'Public lecture',
                'tag'            => 'Governance',
                'duration_label' => '1:12:40',
                'thumbnail_url'  => 'https://mambayya-library.vercel.app/auditorium.jpg',
                'views'          => 2140,
                'published_at'   => '2025-04-20',
            ],
            [
                'title'          => 'Mallam Aminu Kano: A Life in the Service of the Talakawa',
                'kind'           => 'Documentary',
                'tag'            => 'Heritage',
                'duration_label' => '48:05',
                'thumbnail_url'  => 'https://mambayya-library.vercel.app/pillars2.png',
                'views'          => 5380,
                'published_at'   => '2025-03-12',
            ],
            [
                'title'          => 'Using the OPAC: Finding and Reserving Books in Five Minutes',
                'kind'           => 'Training',
                'tag'            => 'Library skills',
                'duration_label' => '26:18',
                'thumbnail_url'  => 'https://mambayya-library.vercel.app/pillars1.png',
                'views'          => 890,
                'published_at'   => '2025-02-02',
            ],
        ];

        foreach ($items as $item) {
            Media::firstOrCreate(['title' => $item['title']], $item);
        }
    }
}
