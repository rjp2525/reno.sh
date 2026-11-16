<?php

declare(strict_types=1);

namespace App\Terminal\Pages;

use App\Models\Education;
use App\Models\Experience;
use App\Models\Skill;
use App\Terminal\Context;
use App\Terminal\Text;
use Illuminate\Support\Collection;

final class ProfessionalPage extends AboutPage
{
    public const TABS = ['experience', 'skills', 'education'];

    private const LEVELS = ['beginner' => 1, 'intermediate' => 2, 'proficient' => 3, 'expert' => 4];

    public function __construct(Context $ctx, private readonly ?string $tab = null)
    {
        parent::__construct($ctx);
    }

    protected function about(): string
    {
        return 'professional';
    }

    protected function body(int $width): string
    {
        $tabs = in_array($this->tab, self::TABS, true) ? [$this->tab] : self::TABS;

        $chunks = [$this->aboutHeader($width, count($tabs) === 1 ? $tabs[0] : null)];
        foreach ($tabs as $tab) {
            $chunks[] = match ($tab) {
                'experience' => $this->experience($width),
                'skills' => $this->skills($width),
                'education' => $this->education($width),
            };
        }
        $chunks[] = $this->aboutFooter($width, count($tabs) === 1 ? array_values(array_diff(self::TABS, $tabs)) : []);

        return $this->stack($chunks);
    }

    private function experience(int $width): string
    {
        $entries = Experience::all()->map(function (Experience $job) use ($width) {
            $title = $this->ansi->style($job->title, 'white', 'bold').$this->ansi->slate(' @ ').$this->ansi->primary($job->company);
            $range = ($this->date($job->start, 'M Y') ?? '?').' – '.($this->date($job->end, 'M Y') ?? 'present');
            $details = [$this->ansi->slate($this->meta([$range, $job->start ? $job->tenure : null, $job->location]))];

            $entry = $this->entry($title, $details, $width);
            $bullets = collect($job->responsibilities ?? [])
                ->map(fn ($r) => is_array($r) ? ($r['responsibility'] ?? $r['text'] ?? reset($r)) : $r)
                ->filter()
                ->map(fn ($r) => Text::wrap($this->ansi->orange('•').' '.$r, $width - 2, '    ', '  '));

            return $bullets->isEmpty() ? $entry : $entry."\n".$bullets->implode("\n");
        })->all();

        return $this->group('experience', $entries, $width);
    }

    private function skills(int $width): string
    {
        /** @var Collection<string, Collection<int, Skill>> $groups */
        $groups = Skill::all()->groupBy('category');
        if ($groups->isEmpty()) {
            return '';
        }

        $nameWidth = min(24, (int) Skill::all()->max(fn (Skill $s) => mb_strwidth($s->name)) + 2);
        $out = [];
        foreach ($groups as $category => $skills) {
            $lines = [$this->ansi->style((string) $category, 'orange', 'bold')];
            foreach ($skills as $skill) {
                $level = self::LEVELS[$skill->proficiency?->value] ?? 0;
                $bar = $this->ansi->primary(str_repeat('●', $level)).$this->ansi->line(str_repeat('○', 4 - $level));
                $detail = $this->meta([$skill->level, $skill->started ? $skill->experience : null]);
                $row = Text::pad($this->ansi->white($skill->name), $nameWidth).$bar.' '.$this->ansi->slate($detail);
                $lines[] = Text::wrap($row, $width, str_repeat(' ', $nameWidth + 7), '  ');
            }
            $out[] = implode("\n", $lines);
        }

        return $this->group('skills', $out, $width);
    }

    private function education(int $width): string
    {
        $entries = Education::all()->map(function (Education $edu) use ($width) {
            $years = implode(' – ', array_filter([$this->date($edu->started, 'Y'), $this->date($edu->graduated, 'Y')]));
            $details = [
                $this->ansi->primary($edu->school_name),
                $this->ansi->slate($this->meta([$years, $edu->location])),
                $edu->minor ? $this->ansi->slate('minor: ').$edu->minor : null,
                $edu->description,
            ];
            foreach (['achievements', 'projects', 'extracurriculars'] as $list) {
                foreach ((array) ($edu->{$list} ?? []) as $item) {
                    $item = is_array($item) ? implode(' ', array_filter($item, 'is_string')) : (string) $item;
                    if ($item !== '') {
                        $details[] = $this->ansi->orange('•').' '.$item;
                    }
                }
            }

            return $this->entry($this->ansi->style($edu->degree, 'white', 'bold'), $details, $width);
        })->all();

        return $this->group('education', $entries, $width);
    }
}
