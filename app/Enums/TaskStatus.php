<?php

namespace App\Enums;

enum TaskStatus: string
{
    case NotStarted = 'not_started';
    case InProgress = 'in_progress';
    case Review = 'review';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::NotStarted => '着手前',
            self::InProgress => '着手中',
            self::Review => 'レビュー',
            self::Completed => '終了',
        };
    }
}
