<?php

namespace App\Notifications;

use App\Models\ProjectInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProjectInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ProjectInvitation $invitation, public string $token)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('プロジェクトへの招待')->line($this->invitation->project?->name.' へ招待されました。')
            ->action('招待を承認', route('invitations.show', $this->token))->line('有効期限: '.$this->invitation->expires_at->format('Y/m/d H:i'));
    }
}
