<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class RegionalDataSyncLog extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'sync_source',
        'status',
        'provinces_created',
        'provinces_updated',
        'cities_created',
        'cities_updated',
        'errors',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'provinces_created' => 'integer',
        'provinces_updated' => 'integer',
        'cities_created' => 'integer',
        'cities_updated' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (!$model->id) {
                $model->id = (string) Str::uuid();
            }
        });
    }
}
