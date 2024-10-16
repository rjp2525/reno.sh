<?php

namespace Database\Seeders;

use App\Enums\SkillProficiency;
use App\Models\Skill;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class SkillSeeder extends Seeder
{
    public function run(): void
    {
        Skill::create([
            'category' => 'Backend',
            'name' => 'PHP',
            'proficiency' => SkillProficiency::EXPERT,
            'started' => Carbon::parse('2011-08-01'),
        ]);

        Skill::create([
            'category' => 'Backend',
            'name' => 'Laravel',
            'proficiency' => SkillProficiency::EXPERT,
            'started' => Carbon::parse('2013-12-12'), // 4.1
        ]);

        Skill::create([
            'category' => 'Backend',
            'name' => 'SQL',
            'proficiency' => SkillProficiency::EXPERT,
            'started' => Carbon::parse('2013-02-05'), // 5.6
        ]);

        Skill::create([
            'category' => 'Backend',
            'name' => 'Node.js',
            'proficiency' => SkillProficiency::PROFICIENT,
            'started' => Carbon::parse('2016-07-21'),
        ]);

        Skill::create([
            'category' => 'Backend',
            'name' => 'Go',
            'proficiency' => SkillProficiency::BEGINNER,
            'started' => Carbon::parse('2018-10-10'),
            'ended' => Carbon::parse('2019-12-14'),
        ]);

        Skill::create([
            'category' => 'Backend',
            'name' => 'Python',
            'proficiency' => SkillProficiency::INTERMEDIATE,
            'started' => Carbon::parse('2017-02-08'),
            'ended' => Carbon::parse('2020-04-14'),
        ]);

        Skill::create([
            'category' => 'Backend',
            'name' => 'Inertia.js',
            'proficiency' => SkillProficiency::EXPERT,
            'started' => Carbon::parse('2021-06-01'),
        ]);

        Skill::create([
            'category' => 'Frontend',
            'name' => 'HTML',
            'proficiency' => SkillProficiency::EXPERT,
            'started' => Carbon::parse('2011-08-01'),
        ]);

        Skill::create([
            'category' => 'Frontend',
            'name' => 'CSS',
            'proficiency' => SkillProficiency::EXPERT,
            'started' => Carbon::parse('2011-08-01'),
        ]);

        Skill::create([
            'category' => 'Frontend',
            'name' => 'JavaScript',
            'proficiency' => SkillProficiency::EXPERT,
            'started' => Carbon::parse('2014-10-01'),
        ]);

        Skill::create([
            'category' => 'Frontend',
            'name' => 'React',
            'proficiency' => SkillProficiency::INTERMEDIATE,
            'started' => Carbon::parse('2016-08-01'),
            'ended' => Carbon::parse('2018-11-14'),
        ]);

        Skill::create([
            'category' => 'Frontend',
            'name' => 'Vue.js',
            'proficiency' => SkillProficiency::EXPERT,
            'started' => Carbon::parse('2017-08-23'),
        ]);

        Skill::create([
            'category' => 'Frontend',
            'name' => 'Tailwind CSS',
            'proficiency' => SkillProficiency::EXPERT,
            'started' => Carbon::parse('2020-05-13'),
        ]);

        Skill::create([
            'category' => 'Frontend',
            'name' => 'TypeScript',
            'proficiency' => SkillProficiency::EXPERT,
            'started' => Carbon::parse('2023-03-23'),
        ]);

        Skill::create([
            'category' => 'Frontend',
            'name' => 'Sass',
            'proficiency' => SkillProficiency::EXPERT,
            'started' => Carbon::parse('2015-04-29'),
        ]);

        Skill::create([
            'category' => 'Tools & DevOps',
            'name' => 'Docker',
            'proficiency' => SkillProficiency::PROFICIENT,
            'started' => Carbon::parse('2020-12-08'),
        ]);

        Skill::create([
            'category' => 'Tools & DevOps',
            'name' => 'GitHub Actions',
            'proficiency' => SkillProficiency::PROFICIENT,
            'started' => Carbon::parse('2020-10-20'),
        ]);

        Skill::create([
            'category' => 'Tools & DevOps',
            'name' => 'Git',
            'proficiency' => SkillProficiency::PROFICIENT,
            'started' => Carbon::parse('2013-02-01'),
        ]);

        Skill::create([
            'category' => 'Tools & DevOps',
            'name' => 'GitHub Actions',
            'proficiency' => SkillProficiency::PROFICIENT,
            'started' => Carbon::parse('2020-10-20'),
        ]);

        Skill::create([
            'category' => 'Tools & DevOps',
            'name' => 'Linux System Administration',
            'proficiency' => SkillProficiency::PROFICIENT,
            'started' => Carbon::parse('2012-11-15'),
        ]);

        Skill::create([
            'category' => 'Tools & DevOps',
            'name' => 'Continuous Integration',
            'proficiency' => SkillProficiency::PROFICIENT,
            'started' => Carbon::parse('2021-05-15'),
        ]);

        Skill::create([
            'category' => 'Other',
            'name' => 'Agile Methodology',
            'proficiency' => SkillProficiency::PROFICIENT,
            'started' => Carbon::parse('2020-10-20'),
        ]);

        Skill::create([
            'category' => 'Other',
            'name' => 'Leadership',
            'proficiency' => SkillProficiency::PROFICIENT,
            'started' => Carbon::parse('2022-08-10'),
        ]);

        Skill::create([
            'category' => 'Other',
            'name' => 'Project Management',
            'proficiency' => SkillProficiency::PROFICIENT,
            'started' => Carbon::parse('2023-06-12'),
        ]);

        Skill::create([
            'category' => 'Other',
            'name' => 'Problem Solving & Critical Thinking',
            'proficiency' => SkillProficiency::EXPERT,
            'started' => Carbon::parse('2016-03-01'),
        ]);

        Skill::create([
            'category' => 'Other',
            'name' => 'Teamwork & Collaboration',
            'proficiency' => SkillProficiency::EXPERT,
            'started' => Carbon::parse('2021-06-22'),
        ]);

        Skill::create([
            'category' => 'Other',
            'name' => 'Attention to Detail',
            'proficiency' => SkillProficiency::EXPERT,
            'started' => Carbon::parse('2011-08-24'),
        ]);

        Skill::create([
            'category' => 'Other',
            'name' => 'Interpersonal Skills',
            'proficiency' => SkillProficiency::INTERMEDIATE,
            'started' => Carbon::parse('2021-06-22'),
        ]);

        Skill::create([
            'category' => 'Other',
            'name' => 'Accountability',
            'proficiency' => SkillProficiency::EXPERT,
            'started' => Carbon::parse('2021-06-22'),
        ]);
    }
}
