<?php

namespace Database\Seeders;

use App\Models\Education;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class EducationSeeder extends Seeder
{
    public function run(): void
    {
        Education::create([
            'school_name' => 'Southern New Hampshire University',
            'location' => 'Manchester, NH',
            'degree' => 'Bachelor of Science - BS, Computer Science',
            'minor' => 'Business Administration',
            'started' => Carbon::parse('2015-09-02'),
            'graduated' => Carbon::parse('2019-05-12'),
        ]);
    }
}
