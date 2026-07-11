<?php

namespace App\Mail;

use App\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactFormSubmitted extends Mailable
{
    use Queueable, SerializesModels;

    public ContactMessage $contactMessage;

    /**
     * Create a new message instance.
     */
    public function __construct(ContactMessage $contactMessage)
    {
        $this->contactMessage = $contactMessage;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New Contact Message - ' . ucfirst($this->contactMessage->category),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $category = ucfirst($this->contactMessage->category);
        $name = htmlspecialchars($this->contactMessage->name);
        $email = htmlspecialchars($this->contactMessage->email);
        $subject = htmlspecialchars($this->contactMessage->subject);
        $messageText = nl2br(htmlspecialchars($this->contactMessage->message));
        $adminUrl = (config('app.url') ?: 'https://prakerin.id') . '/dashboard/contact-messages';

        return new Content(
            htmlString: "
                <div style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e5e7eb; border-radius: 12px;'>
                    <h2 style='color: #4F46E5; border-bottom: 2px solid #e5e7eb; padding-bottom: 10px;'>New Contact Message Received</h2>
                    <p><strong>From:</strong> {$name} ({$email})</p>
                    <p><strong>Category:</strong> {$category}</p>
                    <p><strong>Subject:</strong> {$subject}</p>
                    <div style='background-color: #f9fafb; padding: 15px; border-radius: 8px; margin-top: 15px; border-left: 4px solid #4F46E5;'>
                        <p style='margin: 0; white-space: pre-line;'>{$messageText}</p>
                    </div>
                    <div style='margin-top: 25px;'>
                        <a href='{$adminUrl}' style='display: inline-block; background-color: #4F46E5; color: white; padding: 12px 20px; text-decoration: none; border-radius: 6px; font-weight: bold;'>View in Admin Dashboard</a>
                    </div>
                </div>
            "
        );
    }
}
