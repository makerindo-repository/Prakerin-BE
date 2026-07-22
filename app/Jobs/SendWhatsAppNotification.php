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
        $frontendUrl = config('app.frontend_url', 'http://localhost:3000');

        // Build concise WhatsApp message (plain text, no HTML)
        $link = $this->inboxItem->action_url ?? $frontendUrl . '/dashboard/inbox';

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
