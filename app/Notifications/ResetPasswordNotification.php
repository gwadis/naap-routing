<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends Notification
{
    /**
     * The password reset token.
     *
     * @var string
     */
    public $token;

    /**
     * Create a notification instance.
     *
     * @param  string  $token
     * @return void
     */
    public function __construct(string $token)
    {
        $this->token = $token;
    }

    /**
     * Get the notification's channels.
     *
     * @param  mixed  $notifiable
     * @return array|string
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Build the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $recipientEmail = $notifiable->routeNotificationForMail($this) 
            ?: (method_exists($notifiable, 'getEmailForPasswordReset') ? $notifiable->getEmailForPasswordReset() : null)
            ?: $notifiable->email;

        $url = route('password.reset', [
            'token' => $this->token,
            'email' => $recipientEmail,
        ]);

        $expire = config('auth.passwords.users.expire', 60);

        return (new MailMessage)
            ->subject('Reset Password Notification - NAAP Document Routing')
            ->view('emails.generic', [
                'title' => 'Reset Password Notification',
                'name' => $notifiable->name ?? $notifiable->username ?? 'User',
                'body' => "You are receiving this email because we received a password reset request for your account.<br><br>Click the button below to reset your password. This link will expire in {$expire} minutes.<br><br>If you did not request a password reset, no further action is required.",
                'actionText' => 'Reset Password',
                'actionUrl' => $url,
                'warning' => 'If you did not request a password reset, please secure your account or contact your administrator immediately.',
            ]);
    }

    /**
     * Get the Brevo mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toBrevoMail($notifiable): array
    {
        $recipientEmail = $notifiable->routeNotificationForMail($this) 
            ?: (method_exists($notifiable, 'getEmailForPasswordReset') ? $notifiable->getEmailForPasswordReset() : null)
            ?: $notifiable->email;

        $url = route('password.reset', [
            'token' => $this->token,
            'email' => $recipientEmail,
        ]);

        $expire = config('auth.passwords.users.expire', 60);
        $name = $notifiable->name ?? $notifiable->username ?? 'User';

        $body = view('emails.generic', [
            'title' => 'Reset Password Notification',
            'name' => $name,
            'body' => "You are receiving this email because we received a password reset request for your account.<br><br>Click the button below to reset your password. This link will expire in {$expire} minutes.<br><br>If you did not request a password reset, no further action is required.",
            'actionText' => 'Reset Password',
            'actionUrl' => $url,
            'warning' => 'If you did not request a password reset, please secure your account or contact your administrator immediately.',
        ])->render();

        return [
            'subject' => 'Reset Password Notification - NAAP Document Routing',
            'body' => $body,
            'to' => $recipientEmail,
        ];
    }
}
