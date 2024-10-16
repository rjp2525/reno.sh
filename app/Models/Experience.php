<?php

namespace App\Models;

use App\Models\Scopes\ExperienceEndDateScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[ScopedBy([ExperienceEndDateScope::class])]
class Experience extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'company',
        'location',
        'start',
        'end',
        'responsibilities',
    ];

    protected $casts = [
        'start' => 'date:M Y',
        'end' => 'date:M Y',
        'responsibilities' => 'array',
    ];

    protected $appends = [
        'tenure',
    ];

    protected function tenure(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->calculateTenure()
        );
    }

    protected function calculateTenure(): string
    {
        $started = $this->start;
        $end = $this->end ?? now();

        $diffInMonths = ceil($started->diffInMonths($end));
        $diffInYears = floor($diffInMonths / 12);

        return ($diffInYears >= 1)
            ? sprintf('%s %s', $diffInYears, Str::plural('year', $diffInYears))
            : sprintf('%s %s', $diffInMonths, Str::plural('month', $diffInMonths));
    }
}
