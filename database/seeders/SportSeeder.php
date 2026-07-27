<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SportSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $sports = [
            ['code' => 'football', 'name' => 'Fútbol', 'scoring_label' => 'goals'],
            ['code' => 'futsal', 'name' => 'Fútbol sala', 'scoring_label' => 'goals'],
            ['code' => 'basketball', 'name' => 'Básquet', 'scoring_label' => 'points'],
            ['code' => 'volleyball', 'name' => 'Vóley', 'scoring_label' => 'points'],
            ['code' => 'handball', 'name' => 'Handball', 'scoring_label' => 'goals'],
        ];

        foreach ($sports as $sport) {
            DB::table('sports')->updateOrInsert(
                ['code' => $sport['code']],
                [
                    ...$sport,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }
}
