<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ReportTask extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'task_id',
    ];

    protected static function booted(): void
    {
        static::creating(function (ReportTask $reportTask) {
            $reportTask->id = (string) Str::uuid();
        });
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function reportTaskMessage()
    {
        return $this->hasMany(ReportTaskMessage::class);
    }
}
