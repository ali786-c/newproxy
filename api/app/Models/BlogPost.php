<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlogPost extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'content',
        'category',
        'tags',
        'image_url',
        'image_prompt',
        'image_source',
        'author_id',
        'published_at',
        'reading_time_min',
        'is_draft',
        'meta_title',
        'meta_description',
        'meta_keywords'
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'is_draft' => 'boolean',
        'tags' => 'array',
    ];

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function scopePublished($query)
    {
        return $query->where('is_draft', false)->whereNotNull('published_at');
    }
}
