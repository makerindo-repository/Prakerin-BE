<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ContactMessage extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'email',
        'category',
        'subject',
        'message',
        'status',
        'user_id',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (!$model->id) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function replies()
    {
        return $this->hasMany(ContactReply::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function markAsRead()
    {
        if ($this->status === 'new') {
            $this->status = 'read';
            $this->save();
        }
    }

    public function markAsReplied()
    {
        $this->status = 'replied';
        $this->save();
    }
}
