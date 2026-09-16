<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class SecurityMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $title;
    public $name;
    public $body;
    public $actionText;
    public $actionUrl;
    public $warning;

    public function __construct($title, $name, $body, $actionText = null, $actionUrl = null, $warning = null)
    {
        $this->title = $title;
        $this->name = $name;
        $this->body = $body;
        $this->actionText = $actionText;
        $this->actionUrl = $actionUrl;
        $this->warning = $warning;
    }

    public function build()
    {
        return $this->subject($this->title)
                    ->view('emails.generic');
    }
}
