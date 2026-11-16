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
        Skill::updateOrCreate(['category' => 'Backend', 'name' => 'PHP'], [
            'category' => 'Backend',
            'name' => 'PHP',
            'proficiency' => SkillProficiency::EXPERT,
            'started' => Carbon::parse('2011-08-01'),
        ]);

        Skill::updateOrCreate(['category' => 'Backend', 'name' => 'Laravel'], [
            'category' => 'Backend',
            'name' => 'Laravel',
            'proficiency' => SkillProficiency::EXPERT,
            'started' => Carbon::parse('2013-12-12'), // 4.1
        ]);

        Skill::updateOrCreate(['category' => 'Backend', 'name' => 'SQL'], [
            'category' => 'Backend',
            'name' => 'SQL',
            'proficiency' => SkillProficiency::EXPERT,
            'started' => Carbon::parse('2013-02-05'), // 5.6
        ]);

        Skill::updateOrCreate(['category' => 'Backend', 'name' => 'Node.js'], [
            'category' => 'Backend',
            'name' => 'Node.js',
            'proficiency' => SkillProficiency::PROFICIENT,
            'started' => Carbon::parse('2016-07-21'),
        ]);

        Skill::updateOrCreate(['category' => 'Backend', 'name' => 'Go'], [
            'category' => 'Backend',
            'name' => 'Go',
            'proficiency' => SkillProficiency::BEGINNER,
            'started' => Carbon::parse('2018-10-10'),
            'ended' => Carbon::parse('2019-12-14'),
        ]);

        Skill::updateOrCreate(['category' => 'Backend', 'name' => 'Python'], [
            'category' => 'Backend',
            'name' => 'Python',
            'proficiency' => SkillProficiency::INTERMEDIATE,
            'started' => Carbon::parse('2017-02-08'),
            'ended' => Carbon::parse('2020-04-14'),
        ]);

        Skill::updateOrCreate(['category' => 'Backend', 'name' => 'Inertia.js'], [
            'category' => 'Backend',
            'name' => 'Inertia.js',
            'proficiency' => SkillProficiency::EXPERT,
            'started' => Carbon::parse('2021-06-01'),
        ]);

        Skill::updateOrCreate(['category' => 'Frontend', 'name' => 'HTML'], [
            'category' => 'Frontend',
            'name' => 'HTML',
            'proficiency' => SkillProficiency::EXPERT,
            'started' => Carbon::parse('2011-08-01'),
        ]);

        Skill::updateOrCreate(['category' => 'Frontend', 'name' => 'CSS'], [
            'category' => 'Frontend',
            'name' => 'CSS',
            'proficiency' => SkillProficiency::EXPERT,
            'started' => Carbon::parse('2011-08-01'),
        ]);

        Skill::updateOrCreate(['category' => 'Frontend', 'name' => 'JavaScript'], [
            'category' => 'Frontend',
            'name' => 'JavaScript',
            'proficiency' => SkillProficiency::EXPERT,
            'started' => Carbon::parse('2014-10-01'),
        ]);

        Skill::updateOrCreate(['category' => 'Frontend', 'name' => 'React'], [
            'category' => 'Frontend',
            'name' => 'React',
            'proficiency' => SkillProficiency::INTERMEDIATE,
            'started' => Carbon::parse('2016-08-01'),
            'ended' => Carbon::parse('2018-11-14'),
        ]);

        Skill::updateOrCreate(['category' => 'Frontend', 'name' => 'Vue.js'], [
            'category' => 'Frontend',
            'name' => 'Vue.js',
            'proficiency' => SkillProficiency::EXPERT,
            'started' => Carbon::parse('2017-08-23'),
        ]);

        Skill::updateOrCreate(['category' => 'Frontend', 'name' => 'Tailwind CSS'], [
            'category' => 'Frontend',
            'name' => 'Tailwind CSS',
            'proficiency' => SkillProficiency::EXPERT,
            'started' => Carbon::parse('2020-05-13'),
        ]);

        Skill::updateOrCreate(['category' => 'Frontend', 'name' => 'TypeScript'], [
            'category' => 'Frontend',
            'name' => 'TypeScript',
            'proficiency' => SkillProficiency::EXPERT,
            'started' => Carbon::parse('2023-03-23'),
        ]);

        Skill::updateOrCreate(['category' => 'Frontend', 'name' => 'Sass'], [
            'category' => 'Frontend',
            'name' => 'Sass',
            'proficiency' => SkillProficiency::EXPERT,
            'started' => Carbon::parse('2015-04-29'),
        ]);

        Skill::updateOrCreate(['category' => 'Tools & DevOps', 'name' => 'Docker'], [
            'category' => 'Tools & DevOps',
            'name' => 'Docker',
            'proficiency' => SkillProficiency::PROFICIENT,
            'started' => Carbon::parse('2020-12-08'),
        ]);

        Skill::updateOrCreate(['category' => 'Tools & DevOps', 'name' => 'GitHub Actions'], [
            'category' => 'Tools & DevOps',
            'name' => 'GitHub Actions',
            'proficiency' => SkillProficiency::PROFICIENT,
            'started' => Carbon::parse('2020-10-20'),
        ]);

        Skill::updateOrCreate(['category' => 'Tools & DevOps', 'name' => 'Git'], [
            'category' => 'Tools & DevOps',
            'name' => 'Git',
            'proficiency' => SkillProficiency::PROFICIENT,
            'started' => Carbon::parse('2013-02-01'),
        ]);

        Skill::updateOrCreate(['category' => 'Tools & DevOps', 'name' => 'GitHub Actions'], [
            'category' => 'Tools & DevOps',
            'name' => 'GitHub Actions',
            'proficiency' => SkillProficiency::PROFICIENT,
            'started' => Carbon::parse('2020-10-20'),
        ]);

        Skill::updateOrCreate(['category' => 'Tools & DevOps', 'name' => 'Linux System Administration'], [
            'category' => 'Tools & DevOps',
            'name' => 'Linux System Administration',
            'proficiency' => SkillProficiency::PROFICIENT,
            'started' => Carbon::parse('2012-11-15'),
        ]);

        Skill::updateOrCreate(['category' => 'Tools & DevOps', 'name' => 'Continuous Integration'], [
            'category' => 'Tools & DevOps',
            'name' => 'Continuous Integration',
            'proficiency' => SkillProficiency::PROFICIENT,
            'started' => Carbon::parse('2021-05-15'),
        ]);

        Skill::updateOrCreate(['category' => 'Other', 'name' => 'Agile Methodology'], [
            'category' => 'Other',
            'name' => 'Agile Methodology',
            'proficiency' => SkillProficiency::PROFICIENT,
            'started' => Carbon::parse('2020-10-20'),
        ]);

        Skill::updateOrCreate(['category' => 'Other', 'name' => 'Leadership'], [
            'category' => 'Other',
            'name' => 'Leadership',
            'proficiency' => SkillProficiency::PROFICIENT,
            'started' => Carbon::parse('2022-08-10'),
        ]);

        Skill::updateOrCreate(['category' => 'Other', 'name' => 'Project Management'], [
            'category' => 'Other',
            'name' => 'Project Management',
            'proficiency' => SkillProficiency::PROFICIENT,
            'started' => Carbon::parse('2023-06-12'),
        ]);

        Skill::updateOrCreate(['category' => 'Other', 'name' => 'Problem Solving & Critical Thinking'], [
            'category' => 'Other',
            'name' => 'Problem Solving & Critical Thinking',
            'proficiency' => SkillProficiency::EXPERT,
            'started' => Carbon::parse('2016-03-01'),
        ]);

        Skill::updateOrCreate(['category' => 'Other', 'name' => 'Teamwork & Collaboration'], [
            'category' => 'Other',
            'name' => 'Teamwork & Collaboration',
            'proficiency' => SkillProficiency::EXPERT,
            'started' => Carbon::parse('2021-06-22'),
        ]);

        Skill::updateOrCreate(['category' => 'Other', 'name' => 'Attention to Detail'], [
            'category' => 'Other',
            'name' => 'Attention to Detail',
            'proficiency' => SkillProficiency::EXPERT,
            'started' => Carbon::parse('2011-08-24'),
        ]);

        Skill::updateOrCreate(['category' => 'Other', 'name' => 'Interpersonal Skills'], [
            'category' => 'Other',
            'name' => 'Interpersonal Skills',
            'proficiency' => SkillProficiency::INTERMEDIATE,
            'started' => Carbon::parse('2021-06-22'),
        ]);

        Skill::updateOrCreate(['category' => 'Other', 'name' => 'Accountability'], [
            'category' => 'Other',
            'name' => 'Accountability',
            'proficiency' => SkillProficiency::EXPERT,
            'started' => Carbon::parse('2021-06-22'),
        ]);
    }
}
