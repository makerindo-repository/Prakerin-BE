<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Mou extends Model
{
    use HasFactory;
    public $incrementing = false;
    protected $keyType = 'string';
    protected $casts = [
        'message' => 'array'
    ];
    protected $fillable = [
        'id',
        'company_id',
        'school_id',
        'file',
        'message',
        'start_date',
        'end_date',
        'status',
    ];


    protected static function booted()
    {
        static::creating(function ($sector) {
            if (!$sector->id) {
                $sector->id = (string) Str::uuid();
            }
        });
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }
}
