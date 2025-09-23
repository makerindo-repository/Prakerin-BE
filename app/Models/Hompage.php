<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Hompage extends Model
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
        "name",
        "value"
    ];
}
