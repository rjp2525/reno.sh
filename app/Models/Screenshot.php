<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Screenshot extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'original_filename',
        'user_id',
        'stored_hash',
        'stored_filename',
        'stored_path',
        'filesize',
        'private',
        'animated',
        'original_extension',
        'mime_type',
        'height',
        'width',
        'created_at',
    ];

    protected $casts = [
        'private' => 'boolean',
        'animated' => 'boolean',
    ];
}
