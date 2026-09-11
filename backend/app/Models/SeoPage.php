<?php

namespace App\Models;

use Database\Factories\SeoPageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'page_key',
    'title',
    'description',
    'keywords',
    'canonical_url',
    'og_title',
    'og_description',
    'og_image',
    'twitter_title',
    'twitter_description',
    'twitter_image',
    'robots',
    'schema_json',
])]
class SeoPage extends Model
{
    /** @use HasFactory<SeoPageFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'robots' => 'index,follow',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'schema_json' => 'array',
        ];
    }
}
