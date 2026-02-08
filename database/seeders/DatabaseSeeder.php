<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ExperienceSeeder::class,
            EducationSeeder::class,
            SkillSeeder::class,
            ContentPageSeeder::class,
        ]);
    }
}
