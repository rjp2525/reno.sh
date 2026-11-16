<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Local only: the first ADMIN_EMAILS address with password "password".
        if (app()->isLocal() && ($email = config('site.admin_emails')[0] ?? null)) {
            User::firstOrCreate(
                ['email' => $email],
                ['name' => config('site.name'), 'password' => 'password', 'email_verified_at' => now()],
            );
        }

        $this->call([
            ExperienceSeeder::class,
            EducationSeeder::class,
            SkillSeeder::class,
            ContentPageSeeder::class,
        ]);
    }
}
