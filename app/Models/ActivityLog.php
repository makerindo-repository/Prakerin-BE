<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ActivityLog extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'action',
        'resource_type',
        'resource_id',
        'resource_name',
        'ip_address',
        'user_agent',
        'description',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (!$model->id) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByAction($query, $action)
    {
        return $query->where('action', $action);
    }

    public function scopeByResourceType($query, $type)
    {
        if (is_array($type)) {
            return $query->whereIn('resource_type', $type);
        }
        return $query->where('resource_type', $type);
    }

    public function scopeDateRange($query, $start, $end)
    {
        if ($start) {
            $query->where('created_at', '>=', $start);
        }
        if ($end) {
            $query->where('created_at', '<=', $end);
        }
        return $query;
    }

    public function scopeRecent($query)
    {
        return $query->orderBy('created_at', 'DESC');
    }
}
