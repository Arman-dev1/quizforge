<?php

namespace App\Notifications;

use App\Models\QuizResponse;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewResponse extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public QuizResponse $response,
    ) {}

    public function via(User $notifiable): array
    {
        return array_values(array_filter([
            $notifiable->wantsNotification('new_response', 'database') ? 'database' : null,
            $notifiable->wantsNotification('new_response', 'mail') ? 'mail' : null,
        ]));
    }

    public function toMail(User $notifiable): MailMessage
    {
        $quiz = $this->response->quiz;

        return (new MailMessage)
            ->subject(__('New response on ":quiz"', ['quiz' => $quiz->name]))
            ->line(__('Someone just completed ":quiz".', ['quiz' => $quiz->name]))
            ->action(__('View response'), route('quizzes.responses.show', [$quiz->id, $this->response->id]));
    }

    public function toArray(User $notifiable): array
    {
        $quiz = $this->response->quiz;

        return [
            'type' => 'new_response',
            'icon' => 'inbox',
            'message' => __('New response on ":quiz"', ['quiz' => $quiz->name]),
            'url' => route('quizzes.responses.show', [$quiz->id, $this->response->id], false),
        ];
    }
}
