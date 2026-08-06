<?php

namespace Database\Seeders;

use App\Models\NewsPost;
use Illuminate\Database\Seeder;

class NewsPostSeeder extends Seeder
{
    public function run(): void
    {
        $posts = [
            [
                'tag'          => 'Notice',
                'title'        => 'Library extends opening hours during the examination period',
                'body'         => 'Reading rooms will stay open until 21:00 on weekdays and 17:00 on Saturdays from 11 August to support students revising for end-of-session exams.',
                'icon'         => 'bell',
                'tone'         => 'gold',
                'published_at' => '2026-07-28',
            ],
            [
                'tag'          => 'Update',
                'title'        => 'New Aminu Kano archive materials digitised and now searchable online',
                'body'         => 'Over 400 additional letters, speeches and photographs from the Mambayya House archive have been catalogued and added to the digital collection.',
                'icon'         => 'archive',
                'tone'         => 'amber',
                'published_at' => '2026-07-15',
            ],
            [
                'tag'          => 'Announcement',
                'title'        => 'Call for submissions: student essay competition on democratic governance',
                'body'         => "Undergraduate and postgraduate students are invited to submit essays on civic participation in Nigeria. Winning entries will be added to the library's collection.",
                'icon'         => 'graduation-cap',
                'tone'         => 'purple',
                'published_at' => '2026-07-03',
            ],
        ];

        foreach ($posts as $post) {
            NewsPost::firstOrCreate(['title' => $post['title']], $post);
        }
    }
}
