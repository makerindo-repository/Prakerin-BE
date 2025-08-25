<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Task extends Model
{
    use HasFactory;
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = [
        'id',
        'internship_id',
        'title',
        'description',
        'status',
        'due_date',
    ];

    protected static function booted()
    {
        static::creating(function ($sector) {
            if (!$sector->id) {
                $sector->id = (string) Str::uuid();
            }
        });
    }

    public function internship()
    {
        return $this->belongsTo(Internship::class);
    }

    public function reports()
    {
        return $this->hasMany(Report::class);
    }
}
