<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeNotification extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $frontendUrl = config('app.frontend_url', env('APP_URL', 'http://localhost:5173'));

        return (new MailMessage)
            ->subject('Welcome to Arcade Portal!')
            ->greeting("Hello {$notifiable->username}!")
            ->line("Welcome to Arcade Portal. Your account has been registered as a **{$notifiable->role}**.")
            ->line('You can now browse games, submit high scores, save your progress, or upload new game versions.')
            ->action('Launch Arcade Portal', $frontendUrl)
            ->line('Thank you for joining our gaming community!');
    }
}