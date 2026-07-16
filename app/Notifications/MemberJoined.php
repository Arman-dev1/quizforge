<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MemberJoined extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public User $member,
        public Workspace $workspace,
    ) {}

    public function via(User $notifiable): array
    {
        return array_values(array_filter([
            $notifiable->wantsNotification('member_joined', 'database') ? 'database' : null,
            $notifiable->wantsNotification('member_joined', 'mail') ? 'mail' : null,
        ]));
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__(':name joined :workspace', ['name' => $this->member->name, 'workspace' => $this->workspace->name]))
            ->line(__(':name accepted their invitation to :workspace.', ['name' => $this->member->name, 'workspace' => $this->workspace->name]))
            ->action(__('Manage members'), route('settings.members'));
    }

    public function toArray(User $notifiable): array
    {
        return [
            'type' => 'member_joined',
            'icon' => 'users',
            'message' => __(':name joined ":workspace"', ['name' => $this->member->name, 'workspace' => $this->workspace->name]),
            'url' => route('settings.members', absolute: false),
        ];
    }
}
