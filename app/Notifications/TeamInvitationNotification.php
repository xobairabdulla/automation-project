<?php

namespace App\Notifications;

use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TeamInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly TeamInvitation $invitation,
        private readonly User $invitedBy,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $acceptUrl = url('/team/accept/'.$this->invitation->token);

        return (new MailMessage)
            ->subject("{$this->invitedBy->name} invited you to join their team")
            ->greeting("Hello!")
            ->line("{$this->invitedBy->name} has invited you to join their team as {$this->invitation->role}.")
            ->action('Accept Invitation', $acceptUrl)
            ->line('This invitation expires in 7 days.')
            ->line('If you were not expecting this invitation, you can ignore this email.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'team_invitation',
            'invitation_id' => $this->invitation->id,
            'invited_email' => $this->invitation->email,
            'role' => $this->invitation->role,
            'invited_by' => $this->invitedBy->name,
            'accept_url' => url('/team/accept/'.$this->invitation->token),
        ];
    }
}
