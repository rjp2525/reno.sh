<?php

namespace Database\Seeders;

use App\Models\Experience;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Work history, matching the 2026 resume. Re-runnable: each role is matched on
 * (title, company), and roles listed under `was` are renamed in place rather
 * than duplicated. Rows added by hand (admin or iOS app) are left alone.
 */
class ExperienceSeeder extends Seeder
{
    /** Roles from earlier versions of this seeder that the resume merged or retitled. */
    private const RETIRED = [
        ['title' => 'Lead Software Developer', 'company' => 'Clean Program'],
    ];

    public function run(): void
    {
        foreach (self::RETIRED as $role) {
            Experience::where($role)->delete();
        }

        foreach ($this->roles() as $role) {
            $was = $role['was'] ?? [];
            unset($role['was']);
            $key = ['title' => $role['title'], 'company' => $role['company']];

            $existing = Experience::where($key)->first();
            foreach ($was as $old) {
                $existing ??= Experience::where($old)->first();
            }

            ($existing ?? new Experience)->fill($role)->save();
        }
    }

    /** @return list<array<string, mixed>> */
    private function roles(): array
    {
        return [
            [
                'title' => 'Founder & CTO',
                'company' => 'StockShift, Inc.',
                'location' => 'Denver, CO',
                'start' => Carbon::parse('2024-09-01'),
                'end' => null,
                'responsibilities' => [
                    'Founded StockShift to bring inventory, purchasing, sales, bills of materials, manufacturing, traceability, reporting and automation into one product for small US manufacturers',
                    'Own product direction and engineering strategy. Built 20 domain modules around explicit contracts and a shared application pipeline so permissions, transactions, auditing and API behavior remain consistent',
                    'Built PostgreSQL tenant isolation, tamper-evident audit records and an append-only inventory ledger. Tested cross-tenant attacks, concurrent inventory operations and offline sync recovery scenarios',
                    'Established documented architecture decisions, automated tests and consistent code reviews. Use AI-assisted tooling where helpful while keeping design, security and release decisions under direct review',
                ],
            ],
            [
                'title' => 'Principal Software Engineer',
                'company' => 'Galactic Advisors',
                'was' => [['title' => 'Lead Software Engineer', 'company' => 'Galactic Advisors']],
                'location' => 'Remote',
                'start' => Carbon::parse('2025-09-01'),
                'end' => Carbon::parse('2026-09-01'),
                'responsibilities' => [
                    'Led architecture and delivery for a cybersecurity SaaS platform and managed four engineers across application development, endpoint scanning and infrastructure while continuing to write production code',
                    'Designed and built a Go, Kafka, Protobuf and ClickHouse ingestion pipeline to relieve MySQL write contention. Historical replay testing demonstrated processing capacity equivalent to ~800,000 scans/day, against typical production demand of 5,000–8,000 scans/day',
                    'Built a visual detection-rule editor and PHP query compiler that let security researchers create, test and approve rules without engineering changes to hard-coded SQL',
                    'Introduced required code review and 100% changed-line test coverage, improving defect detection. Mentored a developer into independent ownership of Kafka and ClickHouse integrations and the legacy platform',
                    'Set the technical direction for a next-generation platform and implemented a new scanner, always-on endpoint agent and foundational application components ahead of the planned V2 launch',
                ],
            ],
            [
                'title' => 'Software Engineer',
                'company' => 'Galactic Advisors',
                'location' => 'Remote',
                'start' => Carbon::parse('2024-10-21'),
                'end' => Carbon::parse('2025-09-01'),
                'responsibilities' => [
                    'Developed core Laravel and Vue application functionality across cybersecurity scanning, compliance workflows, reporting and partner-facing features',
                    'Investigated persistent MySQL deadlocks caused by high-volume scan ingestion, evaluated streaming and analytical data stores and laid the groundwork for a Go, Kafka and ClickHouse replacement',
                    'Improved backend processing and reporting workflows, including large-dataset queries, background jobs and internal visibility into long-running tasks',
                ],
            ],
            [
                'title' => 'IT Director / Lead Software Developer',
                'company' => 'Clean Program',
                'was' => [['title' => 'IT Director', 'company' => 'Clean Program']],
                'location' => 'Remote',
                'start' => Carbon::parse('2021-06-22'),
                'end' => Carbon::parse('2024-10-18'),
                'responsibilities' => [
                    'Served as the sole software engineer and later IT Director (from May 2023), owning application delivery, IT operations and technology support across the business',
                    'Designed and A/B-tested product-detail page improvements that increased conversion rate by approximately 20%; support tickets declined 25% during the same period',
                    'Built and maintained internal integrations between storefronts, APIs, manufacturing systems and marketing tools; improved checkout-related script execution by moving processing into a separate application',
                ],
            ],
            [
                'title' => 'Senior Software Engineer',
                'company' => 'MailResponse',
                'location' => 'Hybrid - Denver, CO',
                'start' => Carbon::parse('2023-09-01'),
                'end' => Carbon::parse('2024-05-10'),
                'responsibilities' => [
                    'Built internal Laravel tools for high-volume record filtering and processing, including a Vue and WebSocket interface for workflow creation and job visibility',
                    'Improved complex queries and developed components for a configurable multi-client system; the broader migration was not completed',
                ],
            ],
            [
                'title' => 'Full Stack Software Engineer',
                'company' => 'By the Pixel',
                'was' => [['title' => 'Full Stack Software Engineer', 'company' => 'By The Pixel']],
                'location' => 'Remote',
                'start' => Carbon::parse('2022-10-24'),
                'end' => Carbon::parse('2023-07-14'),
                'responsibilities' => [
                    'Rebuilt a Salesforce inventory import using composable Laravel pipeline actions, cutting batch time from about three hours to one hour with image processing, and to roughly 10 minutes when images required no updates',
                    'Reduced unnecessary large-image downloads with HTTP metadata checks and improved performance on image-heavy pages by replacing expensive Livewire rendering with skeleton loading',
                    'Contributed to Laravel, Docker, MySQL and Redis applications and mentored colleagues on maintainability, design and testing practices',
                ],
            ],
        ];
    }
}
