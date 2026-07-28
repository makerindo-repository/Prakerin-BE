<?php

namespace App\Jobs;

use App\Models\InboxItem;
use App\Models\NotificationLog;
use App\Models\User;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendWhatsAppNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [60, 300, 900]; // 1 min, 5 min, 15 min

    protected InboxItem $inboxItem;
    protected User $user;
    protected int $logId;

    public function __construct(InboxItem $inboxItem, User $user, int $logId)
    {
        $this->inboxItem = $inboxItem;
        $this->user      = $user;
        $this->logId     = $logId;
    }

    public function handle(WhatsAppService $whatsApp): void
    {
        $frontendUrl = rtrim(config('app.frontend_url', 'http://localhost:3000'), '/');
        $rawUrl      = $this->inboxItem->action_url ?? '/dashboard/inbox';
        if (!str_starts_with($rawUrl, 'http://') && !str_starts_with($rawUrl, 'https://')) {
            $link = $frontendUrl . '/' . ltrim($rawUrl, '/');
        } elseif (str_contains($rawUrl, 'localhost') || str_contains($rawUrl, '127.0.0.1')) {
            $parsed = parse_url($rawUrl);
            $path   = ($parsed['path'] ?? '') . (isset($parsed['query']) ? '?' . $parsed['query'] : '') . (isset($parsed['fragment']) ? '#' . $parsed['fragment'] : '');
            $link   = $frontendUrl . '/' . ltrim($path, '/');
        } else {
            $link = $rawUrl;
        }

        $message = "📬 *{$this->inboxItem->title}*\n\n"
            . "{$this->inboxItem->content}\n\n"
            . "🔗 Lihat di Prakerin:\n{$link}";

        try {
            $messageId = $whatsApp->sendMessage($this->user->whatsapp_number, $message);

            NotificationLog::find($this->logId)?->update([
                'status'     => 'sent',
                'sent_at'    => now(),
                'message_id' => $messageId,
            ]);

            Log::info("[SendWhatsAppNotification] Sent to user {$this->user->id}, msgId={$messageId}");

        } catch (\Throwable $e) {
            Log::error("[SendWhatsAppNotification] Failed: " . $e->getMessage());

            NotificationLog::find($this->logId)?->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("[SendWhatsAppNotification] Permanently failed for user {$this->user->id}: " . $exception->getMessage());

        NotificationLog::find($this->logId)?->update([
            'status'        => 'failed',
            'error_message' => $exception->getMessage(),
        ]);
    }
}
