<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InboxItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'sender_id',
        'title',
        'content',
        'type',
        'related_type',
        'related_id',
        'action_url',
        'is_read',
        'notification_sent',
    ];

    protected $casts = [
        'is_read'            => 'boolean',
        'notification_sent'  => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function notificationLogs()
    {
        return $this->hasMany(NotificationLog::class);
    }

    public function markAsRead(): void
    {
        $this->update(['is_read' => true]);
    }

    public function setActionUrlAttribute(?string $value): void
    {
        if (empty($value)) {
            $this->attributes['action_url'] = null;
            return;
        }

        $trimmed = trim($value);

        if (str_starts_with($trimmed, '/')) {
            $this->attributes['action_url'] = $trimmed;
            return;
        }

        $parsed = parse_url($trimmed);
        if (isset($parsed['path']) && str_starts_with($parsed['path'], '/dashboard')) {
            $path = $parsed['path'];
            if (!empty($parsed['query'])) {
                $path .= '?' . $parsed['query'];
            }
            if (!empty($parsed['fragment'])) {
                $path .= '#' . $parsed['fragment'];
            }
            $this->attributes['action_url'] = $path;
            return;
        }

        $this->attributes['action_url'] = $trimmed;
    }

    /**
     * Convenience factory for common notification types.
     */
    public static function createForUser(
        string $userId,
        string $title,
        string $content,
        string $type,
        ?string $actionUrl = null,
        ?string $relatedType = null,
        ?int $relatedId = null,
        ?string $senderId = null
    ): self {
        return self::create([
            'user_id'      => $userId,
            'sender_id'    => $senderId,
            'title'        => $title,
            'content'      => $content,
            'type'         => $type,
            'related_type' => $relatedType,
            'related_id'   => $relatedId,
            'action_url'   => $actionUrl,
        ]);
    }
}
