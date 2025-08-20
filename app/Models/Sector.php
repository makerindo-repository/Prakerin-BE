<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Str;

class Sector extends Model
{
    use HasFactory;
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['id', 'name', 'is_accepted'];

    protected static function booted()
    {
        static::creating(function ($sector) {
            if (!$sector->id) {
                $sector->id = (string) Str::uuid();
            }
        });
    }

    public function companies()
    {
        return $this->hasMany(Company::class);
    }
}
