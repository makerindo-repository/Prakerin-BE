<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class GeneratedCv extends Model
{
    // app/Models/GeneratedCv.php

    public $incrementing = false;
    protected $keyType = 'string';

    protected static function booted()
    {
        static::creating(function ($item) {
            if (!$item->id) {
                $item->id = (string) Str::uuid();
            }
        });
    }

    protected $fillable = [
        'id',
        'user_id',
        'generated_content',
        'source_prompt'
    ];
    protected $casts = [
        'generated_content' => 'array',
    ];
}
