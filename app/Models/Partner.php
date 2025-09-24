<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Partner extends Model
{
    /** @use HasFactory<\Database\Factories\PartnerFactory> */
    use HasFactory;
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
        'name',
        'icon',
        'sector'
    ];

    protected $hidden = [
        'id',
        'created_at',
        'updated_at'
    ];
}
