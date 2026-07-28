<?php

namespace App\Mail;

use App\Models\InboxItem;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InboxNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public InboxItem $inboxItem;

    public function __construct(InboxItem $inboxItem)
    {
        $this->inboxItem = $inboxItem;
    }

    public function build(): self
    {
        $typeLabels = [
            'application_status' => 'Status Lamaran',
            'new_task'           => 'Tugas Baru',
            'report_feedback'    => 'Feedback Laporan',
            'new_application'    => 'Lamaran Masuk',
        ];

        $typeLabel  = $typeLabels[$this->inboxItem->type] ?? ucfirst(str_replace('_', ' ', $this->inboxItem->type));
        $appLogoUrl = \App\Models\Setting::getVal('app_logo');
        $appName    = \App\Models\Setting::getVal('app_name', 'Prakerin Platform');

        $rawActionUrl = $this->inboxItem->action_url;
        $actionUrl    = null;
        if ($rawActionUrl) {
            $frontendUrl = rtrim(config('app.frontend_url', 'http://localhost:3000'), '/');
            if (!str_starts_with($rawActionUrl, 'http://') && !str_starts_with($rawActionUrl, 'https://')) {
                $actionUrl = $frontendUrl . '/' . ltrim($rawActionUrl, '/');
            } elseif (str_contains($rawActionUrl, 'localhost') || str_contains($rawActionUrl, '127.0.0.1')) {
                $parsed = parse_url($rawActionUrl);
                $path   = ($parsed['path'] ?? '') . (isset($parsed['query']) ? '?' . $parsed['query'] : '') . (isset($parsed['fragment']) ? '#' . $parsed['fragment'] : '');
                $actionUrl = $frontendUrl . '/' . ltrim($path, '/');
            } else {
                $actionUrl = $rawActionUrl;
            }
        }

        return $this->subject($this->inboxItem->title . ' — ' . $appName)
                    ->view('emails.inbox-notification')
                    ->with([
                        'title'      => $this->inboxItem->title,
                        'content'    => $this->inboxItem->content,
                        'type'       => $typeLabel,
                        'actionUrl'  => $actionUrl,
                        'userName'   => $this->inboxItem->user->username ?? 'Pengguna',
                        'appLogoUrl' => $appLogoUrl,
                        'appName'    => $appName,
                    ]);
    }
}
