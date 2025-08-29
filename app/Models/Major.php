<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Major extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $casts = [
        'is_accepted' => 'boolean',
    ];

    protected $fillable = [
        'id',
        'name',
        'is_accepted',
        'level',
    ];

    protected static function booted()
    {
        static::creating(function ($major) {
            if (!$major->id) {
                $major->id = (string) Str::uuid();
            }
        });
    }

    public function students()
    {
        return $this->hasMany(Student::class);
    }

}
