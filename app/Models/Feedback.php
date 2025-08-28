<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Feedback extends Model
{
    use HasFactory;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'from_user_id',
        'to_user_id',
        'to_type',
        'rating',
        'text',
    ];

    protected static function booted()
    {
        static::creating(function ($user) {
            if (!$user->id) {
                $user->id = (string) Str::uuid();
            }
        });
    }

    // Relasi ke user (si pemberi feedback)
    public function fromUser()
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    // Relasi ke user (si penerima feedback)
    public function toUser()
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }
}
