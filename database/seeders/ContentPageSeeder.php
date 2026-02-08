<?php

namespace Database\Seeders;

use App\Enums\ContentSection;
use App\Models\ContentPage;
use Illuminate\Database\Seeder;

class ContentPageSeeder extends Seeder
{
    public function run(): void
    {
        ContentPage::updateOrCreate(
            ['section' => ContentSection::PERSONAL, 'slug' => 'bio'],
            [
                'title' => 'bio',
                'icon' => 'person',
                'order' => 0,
                'is_published' => true,
                'content' => [
                    [
                        'type' => 'heading',
                        'data' => [
                            'title' => 'Hey, I\'m Reno',
                            'subtitle' => 'Software engineer, creative builder and outdoor enthusiast based in Colorado.',
                            'level' => 'h2',
                        ],
                    ],
                    [
                        'type' => 'divider',
                        'data' => [
                            'style' => 'gradient',
                        ],
                    ],
                    [
                        'type' => 'rich-text',
                        'data' => [
                            'content' => '<p>I\'ve been building software since high school back in 2011. What started as curiosity quickly turned into an obsession. Born and raised in New Hampshire, I eventually made my way out to Colorado where the mountains and open roads keep me grounded when I\'m not staring at a terminal.</p><p>My sweet spot is the full stack. I love working with <strong>Laravel</strong>, <strong>Vue</strong> and <strong>Tailwind</strong>. It\'s the combo that lets me move fast without sacrificing craft. On the backend side, <strong>Go</strong> has become a real love of mine for building high-performance, scalable systems. There\'s something deeply satisfying about writing tight, efficient code that just <em>hums</em>.</p>',
                        ],
                    ],
                    [
                        'type' => 'callout',
                        'data' => [
                            'type' => 'tip',
                            'content' => '<p>I currently work in <strong>cybersecurity</strong>, where I get to use my favorite stack every single day. Building tools that help keep people safe, using the technologies I\'d choose even if nobody was paying me? Yeah, that\'s the dream.</p>',
                        ],
                    ],
                    [
                        'type' => 'rich-text',
                        'data' => [
                            'content' => '<p>Software isn\'t just what I do for work. It\'s genuinely one of my hobbies. I\'ll spend a Saturday building something just because the idea won\'t leave me alone. This site is a good example of that: a portfolio that doubles as a playground for trying new patterns and tools.</p><p>When I\'m not writing code, you\'ll find me overlanding through the Colorado backcountry, hiking with my German Shepherd Maya, chasing snow or taking photos of whatever catches my eye. I believe the best engineers are the ones who stay curious about everything, not just technology.</p>',
                        ],
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'title' => 'What I value',
                            'subtitle' => null,
                            'level' => 'h3',
                        ],
                    ],
                    [
                        'type' => 'two-columns',
                        'data' => [
                            'left' => '<ul><li><strong>Simplicity over cleverness</strong> - clean code that the next person can actually read</li><li><strong>Ownership</strong> - I care about the whole picture, not just my corner of it</li></ul>',
                            'right' => '<ul><li><strong>Craft</strong> - the details matter, from pixel spacing to query performance</li><li><strong>Curiosity</strong> - if it exists, I probably want to know how it works</li></ul>',
                            'ratio' => '50-50',
                        ],
                    ],
                ],
            ]);

        ContentPage::updateOrCreate(
            ['section' => ContentSection::PERSONAL, 'slug' => 'maya'],
            [
                'title' => 'maya',
                'icon' => 'heart',
                'order' => 1,
                'is_published' => true,
                'content' => [
                    [
                        'type' => 'heading',
                        'data' => [
                            'title' => 'Meet Maya',
                            'subtitle' => 'Eight-year-old German Shepherd. Trail companion. Professional rock collector.',
                            'level' => 'h2',
                        ],
                    ],
                    [
                        'type' => 'divider',
                        'data' => [
                            'style' => 'gradient',
                        ],
                    ],
                    [
                        'type' => 'rich-text',
                        'data' => [
                            'content' => '<p>Maya is my best friend. An eight-year-old black and tan German Shepherd who has been by my side through every adventure, career change and cross-country move. She\'s the kind of dog who listens when you talk to her and never judges. Honestly, she\'s a better listener than most people I know.</p>',
                        ],
                    ],
                    [
                        'type' => 'callout',
                        'data' => [
                            'type' => 'note',
                            'content' => '<p>Maya has the voice of a husky when we\'re out. Full-on barking, whining and <em>talking</em>. At home? Absolute angel. Not a peep. It\'s like she saves all her opinions for the trail.</p>',
                        ],
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'title' => 'The highlights reel',
                            'subtitle' => null,
                            'level' => 'h3',
                        ],
                    ],
                    [
                        'type' => 'two-columns',
                        'data' => [
                            'left' => '<p><strong>Loves:</strong></p><ul><li>Running - she could go for miles and still want more</li><li>Hiking - the muddier the trail, the better</li><li>Fetch - but only with <em>two</em> balls, because giving one back is simply not an option</li><li>Rocks - she collects them like souvenirs (terrible for her teeth, impossible to stop)</li></ul>',
                            'right' => '<p><strong>Personality:</strong></p><ul><li>Vocal on the trail, silent at home</li><li>Stubborn in the most endearing way possible</li><li>Will lean her entire body weight into you for pets</li><li>Somehow knows exactly when you need company</li></ul>',
                            'ratio' => '50-50',
                        ],
                    ],
                    [
                        'type' => 'rich-text',
                        'data' => [
                            'content' => '<p>She\'s been my overlanding co-pilot, my hiking buddy and my reason to get outside even when I\'d rather stay on the couch. Every trail we\'ve walked, every campsite we\'ve set up. She\'s been right there. I love her to the moon and back.</p>',
                        ],
                    ],
                ],
            ]);

        // Hobbies section

        ContentPage::updateOrCreate(
            ['section' => ContentSection::HOBBIES, 'slug' => 'overlanding'],
            [
                'title' => 'overlanding',
                'icon' => 'car',
                'order' => 0,
                'is_published' => true,
                'content' => [
                    [
                        'type' => 'heading',
                        'data' => [
                            'title' => 'Wildcard Overland',
                            'subtitle' => 'Go farther. Stay longer. Don\'t sacrifice comfort.',
                            'level' => 'h2',
                        ],
                    ],
                    [
                        'type' => 'divider',
                        'data' => [
                            'style' => 'gradient',
                        ],
                    ],
                    [
                        'type' => 'rich-text',
                        'data' => [
                            'content' => '<p>Overlanding is the thing that pulls me away from the screen and into the places most people only see in wallpapers. There\'s something about being able to drive deep into the backcountry, way past where the pavement ends, set up camp and still have a comfortable place to sleep, cook and hang out. You don\'t have to rough it to get remote.</p><p>My rig is a <strong>2021 Toyota Tacoma TRD Off-Road</strong> topped with a <strong>Tune M1L truck camper</strong>. An upgrade from the Oru Designs Tenfold that served me well for a long time. The whole setup is built around the idea that you can go farther without giving up the things that make the trip enjoyable.</p>',
                        ],
                    ],
                    [
                        'type' => 'callout',
                        'data' => [
                            'type' => 'info',
                            'content' => '<p>I run my overlanding adventures under <strong><a href="https://wildcardoverland.com">Wildcard Overland</a></strong>. Check it out if you\'re into rigs, routes and remote places.</p>',
                        ],
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'title' => 'Where I go',
                            'subtitle' => null,
                            'level' => 'h3',
                        ],
                    ],
                    [
                        'type' => 'rich-text',
                        'data' => [
                            'content' => '<p>I spend most of my summers exploring the mountains of Colorado with friends who share the same obsession. The <strong>San Juan Mountains</strong> are the annual pilgrimage. There\'s nowhere else in the state that packs that much beauty, challenge and remoteness into one area. Engineer Pass, Cinnamon Pass, Ophir Pass. If it\'s a shelf road with exposure, I\'m probably into it.</p><p>The best part of overlanding isn\'t just the destination. It\'s the community. Spending a week on the trail with good people, cooking dinner at camp, swapping stories around a fire while the stars come out. That\'s the stuff.</p>',
                        ],
                    ],
                ],
            ]);

        ContentPage::updateOrCreate(
            ['section' => ContentSection::HOBBIES, 'slug' => 'photography'],
            [
                'title' => 'photography',
                'icon' => 'camera',
                'order' => 1,
                'is_published' => true,
                'content' => [
                    [
                        'type' => 'heading',
                        'data' => [
                            'title' => 'Photography',
                            'subtitle' => 'Landscape, astro and adventure. If it\'s outside, I\'m probably pointing a camera at it.',
                            'level' => 'h2',
                        ],
                    ],
                    [
                        'type' => 'divider',
                        'data' => [
                            'style' => 'gradient',
                        ],
                    ],
                    [
                        'type' => 'rich-text',
                        'data' => [
                            'content' => '<p>Photography started as a way to remember the places I\'d been and turned into something I genuinely care about getting right. I\'m drawn to <strong>landscapes</strong>, <strong>astrophotography</strong> and <strong>adventure photography</strong>. Basically anything that involves being outside and waiting for the light to do something incredible.</p><p>I\'m not big on street photography, portraits or anything that requires me to interact with other humans. Nature is the subject. It doesn\'t complain about angles or ask to see the back of the camera.</p>',
                        ],
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'title' => 'The gear',
                            'subtitle' => null,
                            'level' => 'h3',
                        ],
                    ],
                    [
                        'type' => 'two-columns',
                        'data' => [
                            'left' => '<p><strong>Current setup:</strong></p><ul><li><strong>Sony A7IV</strong> - full frame, incredible dynamic range and a great gateway into video</li><li><strong>Sigma 24-70mm f/2.8</strong> - the daily driver, sharp and versatile</li><li><strong>Sigma 20mm f/1.4</strong> - the astro lens, wide and fast for starry nights</li></ul>',
                            'right' => '<p><strong>Previous setup:</strong></p><ul><li><strong>Nikon D5500</strong> with a <strong>Sigma 18-35mm f/1.8</strong> - genuinely my favorite lens I\'ve ever owned. Fast, sharp and punched way above its weight class</li></ul><p><em>Rest in peace to that combo. I left it on the roof of my car one day. You can probably guess how that story ends.</em></p>',
                            'ratio' => '50-50',
                        ],
                    ],
                    [
                        'type' => 'callout',
                        'data' => [
                            'type' => 'tip',
                            'content' => '<p>I\'m slowly getting into <strong>video</strong>. It\'s a whole different beast compared to stills, but the Sony A7IV makes it a lot less intimidating. Learning the ropes one shaky clip at a time.</p>',
                        ],
                    ],
                ],
            ]);

        ContentPage::updateOrCreate(
            ['section' => ContentSection::HOBBIES, 'slug' => 'hiking'],
            [
                'title' => 'hiking',
                'icon' => 'hiking',
                'order' => 2,
                'is_published' => true,
                'content' => [
                    [
                        'type' => 'heading',
                        'data' => [
                            'title' => 'Hiking',
                            'subtitle' => 'Thin air, big views and a dog who hikes every mountain twice.',
                            'level' => 'h2',
                        ],
                    ],
                    [
                        'type' => 'divider',
                        'data' => [
                            'style' => 'gradient',
                        ],
                    ],
                    [
                        'type' => 'rich-text',
                        'data' => [
                            'content' => '<p>I hike for the exercise, the views and that feeling you get when you\'re standing on a summit at sunrise before anyone else has even started their approach. There\'s something about being the first one up and coming down when everyone else is heading up. It makes the whole thing feel a little more special.</p><p>Colorado\'s <strong>14ers</strong> are the main draw. If you\'re not familiar, those are the peaks above 14,000 feet. There are 58 of them in the state, and they range from "that was a nice walk" to "I need to rethink my life choices." The <strong>San Juan Mountains</strong> are my favorite area for these, just like they are for overlanding. The peaks there are remote, rugged and stunning in a way that photos don\'t quite capture.</p>',
                        ],
                    ],
                    [
                        'type' => 'callout',
                        'data' => [
                            'type' => 'note',
                            'content' => '<p>Maya comes on every hike. Her Whistle activity tracker usually says she covered <strong>2-3x the distance</strong> by the time we\'re done, running circles around everyone on the trail. She\'s getting older and slowing down a little, but you\'d barely notice with how excited she still gets at the trailhead.</p>',
                        ],
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'title' => 'The approach',
                            'subtitle' => null,
                            'level' => 'h3',
                        ],
                    ],
                    [
                        'type' => 'rich-text',
                        'data' => [
                            'content' => '<p>I\'m a sunrise-or-bust kind of hiker. Alpine starts, headlamps on, coffee in the thermos. The goal is to summit early, enjoy it in peace and be back down before the afternoon weather rolls in. I don\'t backpack. Day hikes are my thing. Get up, push hard, take in the view, come home tired.</p>',
                        ],
                    ],
                ],
            ]);

        ContentPage::updateOrCreate(
            ['section' => ContentSection::HOBBIES, 'slug' => 'snowboarding'],
            [
                'title' => 'snowboarding',
                'icon' => 'snowboard',
                'order' => 3,
                'is_published' => true,
                'content' => [
                    [
                        'type' => 'heading',
                        'data' => [
                            'title' => 'Snowboarding',
                            'subtitle' => 'Learned on East Coast ice. Stayed for Colorado powder.',
                            'level' => 'h2',
                        ],
                    ],
                    [
                        'type' => 'divider',
                        'data' => [
                            'style' => 'gradient',
                        ],
                    ],
                    [
                        'type' => 'rich-text',
                        'data' => [
                            'content' => '<p>I didn\'t grow up doing winter sports. Nobody in my family skied or rode. It wasn\'t until college that a coworker dragged me to the mountain and I strapped into a board for the first time. I learned at night, on the East Coast, where "snow" is a generous term for what\'s actually a thin layer of ice with attitude.</p><p>I was a terrible student in the beginning. Instead of listening to advice, I just kind of threw myself down the mountain over and over until things started clicking. Honestly, that stubbornness probably cost me a few extra bruises, but it worked out in the end.</p>',
                        ],
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'title' => 'Where I ride',
                            'subtitle' => null,
                            'level' => 'h3',
                        ],
                    ],
                    [
                        'type' => 'rich-text',
                        'data' => [
                            'content' => '<p><strong>Copper Mountain</strong> has been my home mountain for the last few years on the <strong>Ikon Pass</strong>. It\'s got a solid mix of terrain, and on a good powder day the back bowls are hard to beat. I\'m a black diamond, trees and back bowl kind of rider. The occasional blue is great for cruising, but I\'m happiest when I\'m finding lines through the trees.</p><p>Park stuff has never been my thing. I\'m there for the snow, not the rails.</p>',
                        ],
                    ],
                    [
                        'type' => 'callout',
                        'data' => [
                            'type' => 'warning',
                            'content' => '<p>I broke my wrist a couple years ago and it was a solid reminder that bones don\'t heal as fast as they used to. I\'ve dialed it back a bit since then. Still riding, just with a little more respect for the mountain. I\'m comfortable calling myself an intermediate rider and I\'m happy staying there.</p>',
                        ],
                    ],
                    [
                        'type' => 'rich-text',
                        'data' => [
                            'content' => '<p>Snowboarding gave me a reason to love winter. Before I started riding, the cold months were just something to get through. Now they\'re something I look forward to. Nothing resets the brain quite like a pow day.</p>',
                        ],
                    ],
                ],
            ]);

        ContentPage::updateOrCreate(
            ['section' => ContentSection::HOBBIES, 'slug' => 'software'],
            [
                'title' => 'software',
                'icon' => 'code',
                'order' => 4,
                'is_published' => true,
                'content' => [
                    [
                        'type' => 'heading',
                        'data' => [
                            'title' => 'Software',
                            'subtitle' => 'Yes, I code for fun too. No, I will not finish all my side projects.',
                            'level' => 'h2',
                        ],
                    ],
                    [
                        'type' => 'divider',
                        'data' => [
                            'style' => 'gradient',
                        ],
                    ],
                    [
                        'type' => 'rich-text',
                        'data' => [
                            'content' => '<p>If you\'re a software engineer, you know the deal. We all have about 30 side projects we swore we\'d finish one day and become millionaires. I\'m absolutely one of those people. The graveyard of half-built SaaS apps on my GitHub is both impressive and slightly embarrassing.</p><p>But that\'s kind of the point. For me, coding outside of work isn\'t about shipping a product. It\'s about scratching the itch. There\'s a problem, and I want to solve it. You write some code, it does something, and life gets a little easier or more interesting. That loop never gets old.</p>',
                        ],
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'title' => 'The project graveyard (greatest hits)',
                            'subtitle' => null,
                            'level' => 'h3',
                        ],
                    ],
                    [
                        'type' => 'two-columns',
                        'data' => [
                            'left' => '<ul><li><strong>Landscape business management</strong> - scheduling, invoicing and crew management for landscaping companies</li><li><strong>Recipe sharing platform</strong> - like the recipe sites you love, minus the 3,000-word life story and ad bloat</li><li><strong>Gaming sites and forums</strong> - community platforms for niche gaming communities</li></ul>',
                            'right' => '<ul><li><strong>Emergency dispatch simulator</strong> - simulated CAD/MDT software for GTA V modded roleplay communities</li><li><strong>Laravel packages</strong> - open source tooling for the ecosystem I use every day</li><li><strong>This very site</strong> - a portfolio that doubles as a playground for every new idea I want to try</li></ul>',
                            'ratio' => '50-50',
                        ],
                    ],
                    [
                        'type' => 'callout',
                        'data' => [
                            'type' => 'info',
                            'content' => '<p>I think what drives it is the problem-solving mindset. Work gives me interesting challenges, but side projects let me chase whatever idea is stuck in my head at 11pm on a Tuesday. It\'s the freedom to build whatever, however, with zero stakeholders and zero deadlines. Just vibes and <code>git commit</code>.</p>',
                        ],
                    ],
                ],
            ]);
    }
}
