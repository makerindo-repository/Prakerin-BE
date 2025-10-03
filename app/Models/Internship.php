<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Internship extends Model
{
    use HasFactory;
    public $incrementing = false;
    protected $keyType = 'string';
    protected $casts = [
        'is_completed' => 'boolean',
    ];

    protected $fillable = [
        'id',
        'internship_application_id',
        'start_date',
        'end_date',
        'is_completed',
        'role',
        'student_id',
        'company_id',
    ];

    protected static function booted()
    {
        static::creating(function ($user) {
            if (!$user->id) {
                $user->id = (string) Str::uuid();
            }
        });
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function internshipApplication()
    {
        return $this->belongsTo(InternshipApplication::class);
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function tests()
{
    return $this->belongsToMany(Test::class, 'internship_test', 'internship_id', 'test_id')
        ->withTimestamps();
}

    public function role()
    {
        return $this->belongsTo(Role::class);
    }


}
