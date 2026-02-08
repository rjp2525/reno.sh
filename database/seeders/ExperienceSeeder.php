<?php

namespace Database\Seeders;

use App\Models\Experience;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ExperienceSeeder extends Seeder
{
    public function run(): void
    {
        Experience::updateOrCreate(
            ['title' => 'Lead Software Developer', 'company' => 'Clean Program'],
            [
                'location' => 'Remote',
                'start' => Carbon::parse('2021-06-22'),
                'end' => Carbon::parse('2023-06-09'),
                'responsibilities' => [
                    'Maintained two Shopify stores by writing custom Shopify Liquid themes, resulting in a 20% increase in sales and a 25% reduction of support tickets',
                    'Crafted new theme components and refined existing templates to optimize user experience and aesthetics as measured by a substantial decrease in user drop-off',
                    'Rebuilt all Shopify Plus (Ruby) line item and shipping scripts with intense focus on performance optimization, testing, debugging and iterative refinement, resulting in a 3X reduction in execution time of complex shipping scripts',
                    'Developed an internal Laravel application to integrate with storefronts, custom APIs, MRP software and marketing platforms, reducing order processing time',
                    'Improved the performance of the CI/CD pipelines through automated testing and an improved release schedule',
                    'Engaged actively with cross-functional teams, ensuring project milestones remained on track even under tight deadlines. Developed product roadmaps to keep teams aligned with organizational goals',
                ],
            ],
        );

        Experience::updateOrCreate(
            ['title' => 'Full Stack Software Engineer', 'company' => 'By The Pixel'],
            [
                'location' => 'Remote',
                'start' => Carbon::parse('2022-10-24'),
                'end' => Carbon::parse('2023-07-14'),
                'responsibilities' => [
                    'Wrote over 80,000 lines of clean, efficient code by closely aligning with project requirements through team collaboration and regular meetings',
                    'Regularly updated key technologies including Laravel, Docker, MySQL, Redis and PHP, ensuring a stable production environment',
                    'Created and maintained detailed documentation, leading to a 20% reduction in debugging time and improved productivity',
                    'Significantly enhanced Transwest\'s backend Salesforce inventory ingest system, reducing response times and increasing processing capacity through database and query optimizations by over 60%',
                    'Rewrote the image serving system and optimized compression, achieving a 70% reduction in TTFB times',
                    'Coached lead and fellow developers on SOLID/DRY/TDD principles in 1:1 meetings and Dev Chats, reducing technical debt and facilitating more unit testing across the entire team',
                ],
            ],
        );

        Experience::updateOrCreate(
            ['title' => 'Senior Software Engineer', 'company' => 'MailResponse'],
            [
                'location' => 'Hybrid - Denver, CO',
                'start' => Carbon::parse('2023-11-29'),
                'end' => Carbon::parse('2024-05-10'),
                'responsibilities' => [
                    'Led and mentored a full-time junior developer, fostering growth and ensuring high-quality code standards',
                    'Completely overhauled a large dataset processing application in Laravel, enhancing efficiency and making it customizable for multiple clients instead of the previous one-application-per-client setup',
                    'Optimized numerous heavy data queries, significantly improving the performance of reporting, filtering and record updating',
                    'Built an internal tool for constructing data processing flows, managing clients and viewing job outputs using Vue and web sockets',
                ],
            ],
        );

        Experience::updateOrCreate(
            ['title' => 'IT Director', 'company' => 'Clean Program'],
            [
                'location' => 'Remote',
                'start' => Carbon::parse('2023-06-09'),
                'end' => Carbon::parse('2024-10-18'),
                'responsibilities' => [
                    'Development of custom Shopify frontend components using Online Store 2.0, TypeScript, React and Tailwind to improve flexibility and user experience',
                    'Continuously improved backend PHP systems while integrating new frontend technologies to align with evolving business and customer needs',
                    'Managed cross-functional collaborations with marketing, customer service and warehouse teams to streamline operational workflows including order fulfillment and customer support processes',
                    'Oversaw the entire IT department, ensuring smooth handling of support tickets and tasks, prioritization of development sprints to meet company objectives and deadlines',
                    'Led key upper management initiatives to align technical direction with business goals, reporting directly to the CEO on IT and business performance metrics',
                ],
            ],
        );

        Experience::updateOrCreate(
            ['title' => 'Software Engineer', 'company' => 'Galactic Advisors'],
            [
                'location' => 'Remote',
                'start' => Carbon::parse('2024-10-21'),
                'end' => Carbon::parse('2025-09-01'),
                'responsibilities' => [
                    'Built a TurboTax-style dynamic questionnaire system for compliance assessments, enabling non-technical users to complete complex security audits through guided, branching workflows',
                    'Drove major performance optimizations across the platform, reducing page load times and query execution through eager loading, caching and database indexing',
                    'Led data normalization efforts across the compliance platform, restructuring schemas to eliminate redundancy and improve query consistency',
                    'Mentored colleagues on Laravel best practices, code architecture and testing patterns through code reviews and pair programming sessions',
                    'Contributed to the Compliance-as-a-Service (CaaS) platform used by MSPs to manage security compliance for their clients at scale',
                ],
            ],
        );

        Experience::updateOrCreate(
            ['title' => 'Lead Software Engineer', 'company' => 'Galactic Advisors'],
            [
                'location' => 'Remote',
                'start' => Carbon::parse('2025-09-01'),
                'end' => null,
                'responsibilities' => [
                    'Leading the PHP team, setting technical direction and architecture standards for the compliance platform',
                    'Revamping data storage by migrating to ClickHouse and a Go-based service for high-performance analytical queries',
                    'Implementing Kafka for high-throughput data ingestion, enabling real-time processing of security telemetry at scale',
                    'Designing and building a fully dynamic rule engine that empowers the security team to create detection logic without requiring developers to write queries',
                    'Driving the transition from monolithic query patterns to an event-driven architecture for improved scalability and maintainability',
                ],
            ],
        );
    }
}
