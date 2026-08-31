<?php

namespace App\Notifications;

use App\Models\TaskComment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskCommentPostedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public TaskComment $comment)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $task = $this->comment->task;

        return (new MailMessage)->subject('新しいコメント: '.$task->title)->greeting($notifiable->name.'さん')
            ->line($this->comment->user?->name.' がコメントしました。')->line($this->comment->body)
            ->action('コメントを確認', route('tasks.show', [$task->project, $task]));
    }
}
