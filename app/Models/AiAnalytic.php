<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AiAnalytic extends Model
{
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
        'file_name',
        'file_path',
        'analysis_result'
    ];

    protected $casts = [
        'analysis_result' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
