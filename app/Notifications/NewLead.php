<?php

namespace App\Notifications;

use App\Models\QuizResponse;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewLead extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public QuizResponse $response,
        public string $email,
    ) {}

    public function via(User $notifiable): array
    {
        return array_values(array_filter([
            $notifiable->wantsNotification('new_lead', 'database') ? 'database' : null,
            $notifiable->wantsNotification('new_lead', 'mail') ? 'mail' : null,
        ]));
    }

    public function toMail(User $notifiable): MailMessage
    {
        $quiz = $this->response->quiz;

        return (new MailMessage)
            ->subject(__('New lead: :email', ['email' => $this->email]))
            ->line(__(':email left their contact details on ":quiz".', ['email' => $this->email, 'quiz' => $quiz->name]))
            ->action(__('View lead'), route('quizzes.responses.show', [$quiz->id, $this->response->id]));
    }

    public function toArray(User $notifiable): array
    {
        $quiz = $this->response->quiz;

        return [
            'type' => 'new_lead',
            'icon' => 'user-plus',
            'message' => __('New lead: :email on ":quiz"', ['email' => $this->email, 'quiz' => $quiz->name]),
            'url' => route('quizzes.responses.show', [$quiz->id, $this->response->id], false),
        ];
    }
}
