<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Education extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_name',
        'location',
        'degree',
        'minor',
        'started',
        'graduated',
        'description',
        'achievements',
        'projects',
        'extracurriculars',
    ];

    protected $casts = [
        'started' => 'date:Y',
        'graduated' => 'date:Y',
        'achievements' => 'array',
        'projects' => 'array',
        'extracurriculars' => 'array',
    ];
}
