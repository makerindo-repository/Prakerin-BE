<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

use App\Traits\LogsActivity;

class Mou extends Model
{
    use HasFactory, LogsActivity;
    public $incrementing = false;
    protected $keyType = 'string';
    protected $casts = [
        'message' => 'array',
        'is_company_accepted' => 'boolean',
        'is_school_accepted' => 'boolean',
    ];
    protected $fillable = [
        'id',
        'company_id',
        'school_id',
        'file',
        'message',
        'reason',
        'start_date',
        'end_date',
        'status',
        'is_company_accepted',
        'is_school_accepted'
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
