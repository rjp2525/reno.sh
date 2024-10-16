<?php

namespace App\Models;

use App\Enums\SkillProficiency;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Skill extends Model
{
    use HasFactory;

    protected $fillable = [
        'category',
        'name',
        'proficiency',
        'started',
        'ended',
    ];

    protected $casts = [
        'proficiency' => SkillProficiency::class,
        'started' => 'date:M Y',
        'ended' => 'date:M Y',
    ];

    protected $appends = [
        'experience',
        'level',
    ];

    protected function level(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->proficiency->getLabel()
        );
    }

    protected function experience(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->calculateExperience()
        );
    }

    protected function calculateExperience(): string
    {
        $started = $this->started;
        $end = $this->ended ?? now();

        $diffInMonths = $started->diffInMonths($end);
        $diffInYears = floor($diffInMonths / 12);

        return ($diffInYears >= 1)
            ? sprintf('%s %s', $diffInYears, Str::plural('year', $diffInYears))
            : sprintf('%s %s', $diffInMonths, Str::plural('month', $diffInMonths));
    }
}
