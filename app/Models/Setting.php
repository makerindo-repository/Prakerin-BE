<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'type',
    ];

    /**
     * Get a casted setting value by key.
     */
    public static function getVal(string $key, $default = null)
    {
        $setting = self::where('key', $key)->first();
        if (!$setting) {
            return $default;
        }

        $value = $setting->value;

        // Force string type for IDs, phone numbers, keys, and tokens
        if (str_contains($key, 'id') || str_contains($key, 'number') || str_contains($key, 'key') || str_contains($key, 'token') || str_starts_with($key, 'whatsapp_')) {
            return (string) $value;
        }

        switch ($setting->type) {
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'number':
                return is_numeric($value) ? (float) $value : $value;
            case 'json':
                return json_decode($value, true);
            default:
                return (string) $value;
        }
    }

    /**
     * Send WhatsApp notification (stub).
     */
    public static function sendWhatsAppNotification(string $phone, string $messageText)
    {
        \Illuminate\Support\Facades\Log::info("WhatsApp Notification Dispatch to $phone: $messageText");
    }
}
