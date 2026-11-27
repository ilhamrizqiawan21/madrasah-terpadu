<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Surah extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'number',
        'name_latin',
        'name_arabic',
        'ayah_count',
        'juz_start',
    ];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'ayah_count' => 'integer',
            'juz_start' => 'integer',
        ];
    }
}
