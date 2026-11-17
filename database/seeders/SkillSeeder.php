<?php

namespace Database\Seeders;

use App\Enums\SkillProficiency as P;
use App\Models\Skill;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Skills, matching the 2026 resume. Re-runnable: matched on (category, name).
 * Start dates for the newer entries are the start of the earliest resume role
 * that uses them; adjust in the admin or iOS app as needed.
 */
class SkillSeeder extends Seeder
{
    public function run(): void
    {
        // SQL moved from Backend to Data; rename rather than duplicate.
        if (! Skill::where(['category' => 'Data', 'name' => 'SQL'])->exists()) {
            Skill::where(['category' => 'Backend', 'name' => 'SQL'])->update(['category' => 'Data']);
        }

        foreach ($this->skills() as $category => $skills) {
            foreach ($skills as [$name, $proficiency, $started, $ended]) {
                Skill::updateOrCreate(['category' => $category, 'name' => $name], [
                    'proficiency' => $proficiency,
                    'started' => Carbon::parse($started),
                    'ended' => $ended ? Carbon::parse($ended) : null,
                ]);
            }
        }
    }

    /** @return array<string, list<array{string, P, string, ?string}>> */
    private function skills(): array
    {
        return [
            'Backend' => [
                ['PHP', P::EXPERT, '2011-08-01', null],
                ['Laravel', P::EXPERT, '2013-12-12', null], // 4.1
                ['Go', P::PROFICIENT, '2018-10-10', null],
                ['Inertia.js', P::EXPERT, '2021-06-01', null],
                ['REST APIs & OpenAPI', P::EXPERT, '2013-12-12', null],
                ['Rust', P::BEGINNER, '2024-09-01', null],
                ['Node.js', P::PROFICIENT, '2016-07-21', null],
                ['Python', P::INTERMEDIATE, '2017-02-08', '2020-04-14'],
            ],
            'Frontend' => [
                ['TypeScript', P::EXPERT, '2023-03-23', null],
                ['JavaScript', P::EXPERT, '2014-10-01', null],
                ['Vue.js', P::EXPERT, '2017-08-23', null],
                ['React', P::INTERMEDIATE, '2016-08-01', '2018-11-14'],
                ['HTML', P::EXPERT, '2011-08-01', null],
                ['CSS', P::EXPERT, '2011-08-01', null],
                ['Tailwind CSS', P::EXPERT, '2020-05-13', null],
                ['Sass', P::EXPERT, '2015-04-29', null],
            ],
            'Data' => [
                ['SQL', P::EXPERT, '2013-02-05', null], // MySQL 5.6
                ['PostgreSQL', P::PROFICIENT, '2024-09-01', null],
                ['ClickHouse', P::PROFICIENT, '2024-10-21', null],
                ['Kafka', P::PROFICIENT, '2024-10-21', null],
                ['Redis', P::PROFICIENT, '2022-10-24', null],
            ],
            'Tools & DevOps' => [
                ['Docker', P::PROFICIENT, '2020-12-08', null],
                ['Kubernetes & EKS', P::INTERMEDIATE, '2025-09-01', null],
                ['AWS', P::INTERMEDIATE, '2025-09-01', null],
                ['Pulumi', P::INTERMEDIATE, '2025-09-01', null],
                ['Argo CD', P::INTERMEDIATE, '2025-09-01', null],
                ['GitHub Actions', P::PROFICIENT, '2020-10-20', null],
                ['Continuous Integration', P::PROFICIENT, '2021-05-15', null],
                ['Git', P::PROFICIENT, '2013-02-01', null],
                ['Linux System Administration', P::PROFICIENT, '2012-11-15', null],
            ],
            'Practices' => [
                ['System Design', P::EXPERT, '2021-06-22', null],
                ['Distributed Processing', P::PROFICIENT, '2024-10-21', null],
                ['Multi-tenancy', P::PROFICIENT, '2024-09-01', null],
                ['Security', P::PROFICIENT, '2024-10-21', null],
                ['Automated Testing', P::EXPERT, '2022-10-24', null],
                ['Code Review & Mentoring', P::EXPERT, '2022-10-24', null],
            ],
            'Other' => [
                ['Leadership', P::PROFICIENT, '2022-08-10', null],
                ['Project Management', P::PROFICIENT, '2023-06-12', null],
                ['Agile Methodology', P::PROFICIENT, '2020-10-20', null],
                ['Problem Solving & Critical Thinking', P::EXPERT, '2016-03-01', null],
                ['Teamwork & Collaboration', P::EXPERT, '2021-06-22', null],
                ['Attention to Detail', P::EXPERT, '2011-08-24', null],
                ['Interpersonal Skills', P::INTERMEDIATE, '2021-06-22', null],
                ['Accountability', P::EXPERT, '2021-06-22', null],
            ],
        ];
    }
}
