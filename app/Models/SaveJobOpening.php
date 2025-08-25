<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaveJobOpening extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'job_opening_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function jobOpening()
    {
        return $this->belongsTo(JobOpening::class);
    }
}
