<?php

namespace App\Models;

use App\Enums\AboutPageSection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AboutPage extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'section',
        'order',
        'content',
    ];

    protected $casts = [
        'section' => AboutPageSection::class,
        'content' => 'array',
        'order' => 'integer',
    ];

    protected static function booted()
    {
        static::addGlobalScope('ordered', function ($builder) {
            $builder->orderBy('order');
        });
    }
}
