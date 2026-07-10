<?php

namespace App\Traits;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

trait LogsActivity
{
    protected static function bootLogsActivity()
    {
        static::created(function ($model) {
            self::logActivity('create', $model);
        });

        static::updated(function ($model) {
            self::logActivity('update', $model);
        });

        static::deleted(function ($model) {
            self::logActivity('delete', $model);
        });
    }

    protected static function logActivity($action, $model)
    {
        $userId = Auth::id();
        if (!$userId) {
            return; // Only log authenticated actions
        }

        ActivityLog::create([
            'user_id' => $userId,
            'action' => $action,
            'resource_type' => class_basename($model),
            'resource_id' => $model->id,
            'resource_name' => $model->name ?? $model->title ?? $model->username ?? 'Record',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'description' => ucfirst($action) . "d " . class_basename($model) . ": " . ($model->name ?? $model->title ?? $model->username ?? $model->id),
        ]);
    }
}
