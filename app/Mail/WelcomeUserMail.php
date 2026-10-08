<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeUserMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $user;
    public $temporaryPassword;
    public $password;
    public $activationUrl;
    public $loginUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, ?string $temporaryPassword = null, ?string $activationUrl = null)
    {
        $this->user = $user;
        $this->temporaryPassword = $temporaryPassword;
        $this->password = $temporaryPassword; // For backwards compatibility
        $this->activationUrl = $activationUrl;
        $this->loginUrl = route('login');
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to NAAP Document Routing - Your Account Credentials',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.welcome',
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}
