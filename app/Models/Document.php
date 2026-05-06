<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'source',
        'url',
        'title',
        'section',
        'content',
        'content_hash',
        'metadata',
        'indexed_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'indexed_at' => 'datetime',
    ];
}
