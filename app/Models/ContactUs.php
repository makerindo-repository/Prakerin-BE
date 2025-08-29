<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ContactUs extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $casts = [
        'is_read' => 'boolean',
    ];

    protected $fillable = [
        'id',
        'name',
        'email',
        'message',
        'is_read',
    ];


    protected static function booted()
    {
        static::creating(function ($user) {
            if (!$user->id) {
                $user->id = (string) Str::uuid();
            }
        });
    }
}
