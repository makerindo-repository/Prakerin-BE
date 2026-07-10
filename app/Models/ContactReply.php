<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ContactReply extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'contact_message_id',
        'replied_by_id',
        'reply_message',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (!$model->id) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function contactMessage()
    {
        return $this->belongsTo(ContactMessage::class);
    }

    public function repliedBy()
    {
        return $this->belongsTo(User::class, 'replied_by_id');
    }
}
