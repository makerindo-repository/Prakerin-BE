<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Application extends Model
{
    protected $fillable = [
        'internship_id',
        'student_id',
        'status',
        'cv',
        'step',
    ];

    use HasFactory;
}
