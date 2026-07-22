<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'inbox_item_id',
        'notification_type',
        'channel',
        'status',
        'message_id',
        'error_message',
        'sent_at',
        'delivered_at',
        'read_at',
    ];

    protected $casts = [
        'sent_at'      => 'datetime',
        'delivered_at' => 'datetime',
        'read_at'      => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function inboxItem()
    {
        return $this->belongsTo(InboxItem::class);
    }
}
