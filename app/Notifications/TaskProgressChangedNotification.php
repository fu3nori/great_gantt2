<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskProgressChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Task $task, public int $before, public int $after, public string $actor)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('進捗更新: '.$this->task->title)->greeting($notifiable->name.'さん')
            ->line($this->task->project->name.' / '.$this->task->title)
            ->line("{$this->actor} が進捗を {$this->before}% から {$this->after}% に変更しました。")
            ->action('タスクを確認', route('tasks.show', [$this->task->project, $this->task]));
    }
}
