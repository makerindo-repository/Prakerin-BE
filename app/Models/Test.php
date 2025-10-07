<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Test extends Model
{
    use HasFactory;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'company_id',
        "title",
        "link",
        "description",
        "type"
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

    public function jobOpening()
    {
        return $this->belongsToMany(JobOpening::class);
    }

    public function internshipApplication(){
        return $this->belongsToMany(InternshipApplication::class);
    }

    public function internshipApplicationTests()
    {
        return $this->hasMany(InternshipApplicationTest::class);
    }
}
