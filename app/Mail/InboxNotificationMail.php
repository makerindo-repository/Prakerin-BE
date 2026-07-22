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

        $typeLabel = $typeLabels[$this->inboxItem->type] ?? ucfirst(str_replace('_', ' ', $this->inboxItem->type));

        return $this->subject($this->inboxItem->title . ' — Prakerin')
                    ->view('emails.inbox-notification')
                    ->with([
                        'title'      => $this->inboxItem->title,
                        'content'    => $this->inboxItem->content,
                        'type'       => $typeLabel,
                        'actionUrl'  => $this->inboxItem->action_url,
                        'userName'   => $this->inboxItem->user->username ?? 'Pengguna',
                    ]);
    }
}
