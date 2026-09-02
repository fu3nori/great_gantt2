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
        $position = $this->invitation->role === 'pm' ? 'PM' : 'メンバー';

        return (new MailMessage)
            ->subject('【Great Gantt】プロジェクトへの招待')
            ->greeting(($this->invitation->name ?: 'ご担当者').' 様')
            ->line(($this->invitation->inviter?->name ?: 'プロジェクト管理者').'さんから「'.$this->invitation->project?->name.'」へ招待されました。')
            ->line('ポジション: '.$position)
            ->action('招待内容を確認して参加する', route('invitations.show', $this->token))
            ->line('有効期限: '.$this->invitation->expires_at->format('Y/m/d H:i'))
            ->line('この招待に心当たりがない場合は、このメールを破棄してください。');
    }
}
