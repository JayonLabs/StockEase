<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactInquiryMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @param  string  $senderName  Full name of the person who submitted the form.
     * @param  string  $senderEmail  Email address of the sender, set as Reply-To.
     * @param  string  $inquirySubject  Subject line provided by the sender.
     * @param  string  $body  Message body provided by the sender.
     */
    public function __construct(
        public string $senderName,
        public string $senderEmail,
        public string $inquirySubject,
        public string $body,
    ) {}

    /**
     * Build the message envelope including subject and reply-to address.
     *
     * Setting Reply-To to the sender's address lets the admin reply directly
     * from their email client without manually copying the address.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[Pertanyaan] {$this->inquirySubject}",
            replyTo: [
                new Address($this->senderEmail, $this->senderName),
            ],
        );
    }

    /**
     * Define the view and variables passed to the email template.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mail.contact.inquiry',
        );
    }
}
