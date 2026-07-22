<?php

namespace App\Observers;

use App\Models\InboxItem;
use App\Services\NotificationService;

class InboxItemObserver
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Fires after a new InboxItem row is inserted.
     * NotificationService guards against duplicate sends via notification_sent flag.
     */
    public function created(InboxItem $inboxItem): void
    {
        $this->notificationService->sendInboxNotification($inboxItem);
    }
}
