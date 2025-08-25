<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobOpening extends Model
{
    use HasFactory;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'company_id',
        'field_id',
        'title',
        'description',
        'duration',
        'is_paid',
        'grade',
        'type',
        'qouta',
        'is_available',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function field()
    {
        return $this->belongsTo(Field::class);
    }

    public function internshipApplications()
    {
        return $this->hasMany(InternshipApplication::class);
    }
}
