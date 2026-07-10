<?php

namespace App\Mail;

use App\Models\ContactMessage;
use App\Models\ContactReply;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactReplyNotification extends Mailable
{
    use Queueable, SerializesModels;

    public ContactMessage $contactMessage;
    public ContactReply $contactReply;

    /**
     * Create a new message instance.
     */
    public function __construct(ContactMessage $contactMessage, ContactReply $contactReply)
    {
        $this->contactMessage = $contactMessage;
        $this->contactReply = $contactReply;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Re: ' . $this->contactMessage->subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $subject = htmlspecialchars($this->contactMessage->subject);
        $replyText = nl2br(htmlspecialchars($this->contactReply->reply_message));
        $checkUrl = (config('app.url') ?: 'https://prakerin.id') . '/hubungi-kami?email=' . urlencode($this->contactMessage->email);

        return new Content(
            htmlString: "
                <div style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e5e7eb; border-radius: 12px;'>
                    <h2 style='color: #4F46E5; border-bottom: 2px solid #e5e7eb; padding-bottom: 10px;'>Reply to: {$subject}</h2>
                    <p>Halo,</p>
                    <p>Pesan yang Anda kirimkan melalui form Hubungi Kami telah mendapatkan tanggapan dari tim Admin:</p>
                    
                    <div style='background-color: #f9fafb; padding: 15px; border-radius: 8px; margin-top: 15px; border-left: 4px solid #4F46E5; font-style: italic;'>
                        <p style='margin: 0; white-space: pre-line;'>{$replyText}</p>
                    </div>
                    
                    <p style='margin-top: 20px;'>Anda dapat melihat seluruh riwayat percakapan dengan mengklik tombol di bawah ini:</p>
                    <div style='margin-top: 15px;'>
                        <a href='{$checkUrl}' style='display: inline-block; background-color: #4F46E5; color: white; padding: 12px 20px; text-decoration: none; border-radius: 6px; font-weight: bold;'>Lihat Detail Balasan</a>
                    </div>
                </div>
            "
        );
    }
}
