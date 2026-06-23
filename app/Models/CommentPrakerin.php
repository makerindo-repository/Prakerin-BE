<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Str;

class CommentPrakerin extends Model
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
        'photo_profile',
        'name',
        'position',
        'comment',
    ];
}
