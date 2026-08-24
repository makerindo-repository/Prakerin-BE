<?php

namespace App\Services;

use App\Jobs\SendEmailNotification;
use App\Jobs\SendWhatsAppNotification;
use App\Models\InboxItem;
use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    protected WhatsAppService $whatsApp;

    public function __construct(WhatsAppService $whatsApp)
    {
        $this->whatsApp = $whatsApp;
    }

    /**
     * Primary entry point: queue notifications for a new InboxItem.
     */
    public function sendInboxNotification(InboxItem $inboxItem): void
    {
        // Guard: already sent
        if ($inboxItem->notification_sent) {
            Log::info("[NotificationService] Already sent for inbox_item #{$inboxItem->id}, skipping.");
            return;
        }

        $user = $inboxItem->user;

        if (!$user) {
            Log::warning("[NotificationService] User not found for inbox_item #{$inboxItem->id}");
            return;
        }

        try {
            // Email channel
            if ($user->email_notifications_enabled && $user->email) {
                $this->queueEmail($inboxItem, $user);
            }

            // WhatsApp channel
            if ($user->whatsapp_notifications_enabled && $user->whatsapp_number && $this->whatsApp->isConfigured()) {
                $this->queueWhatsApp($inboxItem, $user);
            }

            $inboxItem->update(['notification_sent' => true]);

            Log::info("[NotificationService] Notifications queued for inbox_item #{$inboxItem->id}, user {$user->id}");
        } catch (\Throwable $e) {
            Log::error("[NotificationService] Failed to queue notifications: " . $e->getMessage());
        }
    }

    /**
     * Shortcut: create an InboxItem for a user and immediately trigger notifications.
     *
     * @param  string      $userId     Recipient user UUID
     * @param  string      $title
     * @param  string      $content
     * @param  string      $type       e.g. 'application_status', 'new_task', 'report_feedback', 'new_application'
     * @param  string|null $actionUrl  Deep link in the frontend
     * @param  string|null $relatedType e.g. 'InternshipApplication'
     * @param  int|null    $relatedId
     * @param  string|null $senderId   Who triggered this (another user UUID or null for system)
     */
    public function notify(
        string $userId,
        string $title,
        string $content,
        string $type,
        ?string $actionUrl = null,
        ?string $relatedType = null,
        string|int|null $relatedId = null,
        ?string $senderId = null
    ): InboxItem {
        $inboxItem = InboxItem::createForUser(
            $userId,
            $title,
            $content,
            $type,
            $actionUrl,
            $relatedType,
            $relatedId,
            $senderId
        );

        // InboxItemObserver handles sendInboxNotification automatically on 'created' event
        return $inboxItem;
    }

    // ─── Private queue helpers ─────────────────────────────────────────────

    private function queueEmail(InboxItem $inboxItem, User $user): void
    {
        $log = NotificationLog::create([
            'user_id'           => $user->id,
            'inbox_item_id'     => $inboxItem->id,
            'notification_type' => $inboxItem->type,
            'channel'           => 'email',
            'status'            => 'queued',
        ]);

        try {
            SendEmailNotification::dispatch($inboxItem, $user, $log->id)->afterResponse();
            Log::info("[NotificationService] Email job queued for user {$user->id}");
        } catch (\Throwable $e) {
            $log->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            Log::error("[NotificationService] Failed to dispatch email job: " . $e->getMessage());
        }
    }

    private function queueWhatsApp(InboxItem $inboxItem, User $user): void
    {
        $log = NotificationLog::create([
            'user_id'           => $user->id,
            'inbox_item_id'     => $inboxItem->id,
            'notification_type' => $inboxItem->type,
            'channel'           => 'whatsapp',
            'status'            => 'queued',
        ]);

        try {
            SendWhatsAppNotification::dispatch($inboxItem, $user, $log->id)->afterResponse();
            Log::info("[NotificationService] WhatsApp job queued for user {$user->id}");
        } catch (\Throwable $e) {
            $log->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            Log::error("[NotificationService] Failed to dispatch WhatsApp job: " . $e->getMessage());
        }
    }
}
