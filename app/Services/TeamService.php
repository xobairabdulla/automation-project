<?php

namespace App\Services;

use App\Models\EmailLog;
use App\Models\Role;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Notifications\TeamInvitationNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use InvalidArgumentException;

class TeamService
{
    private const ALLOWED_ROLES = ['agent', 'support-admin'];

    public function invite(User $owner, string $email, string $role): TeamInvitation
    {
        if (! in_array($role, self::ALLOWED_ROLES, true)) {
            throw new InvalidArgumentException("Role '{$role}' is not allowed for team members.");
        }

        $existingMember = User::where('owner_id', $owner->id)->where('email', $email)->first();
        if ($existingMember) {
            throw new InvalidArgumentException('A team member with that email already exists.');
        }

        // Revoke any existing pending invitation for same owner + email
        TeamInvitation::where('owner_id', $owner->id)
            ->where('email', $email)
            ->whereNull('accepted_at')
            ->delete();

        $invitation = TeamInvitation::create([
            'owner_id' => $owner->id,
            'email' => $email,
            'role' => $role,
            'token' => Str::random(64),
            'expires_at' => now()->addDays(7),
        ]);

        // Mail goes to the invited person's email
        Notification::route('mail', $invitation->email)
            ->notify(new TeamInvitationNotification($invitation, $owner));

        EmailLog::record(
            toEmail: $invitation->email,
            subject: "{$owner->name} invited you to join their team",
            userId: $owner->id,
            tenantId: $owner->id,
        );

        return $invitation;
    }

    public function acceptInvitation(TeamInvitation $invitation, string $name, string $password): User
    {
        if ($invitation->isExpired()) {
            throw new InvalidArgumentException('This invitation has expired.');
        }

        if (! $invitation->isPending()) {
            throw new InvalidArgumentException('This invitation has already been accepted.');
        }

        $member = User::create([
            'owner_id' => $invitation->owner_id,
            'name' => $name,
            'email' => $invitation->email,
            'password' => Hash::make($password),
            'status' => 'active',
        ]);

        $role = Role::where('slug', $invitation->role)->first();
        if ($role) {
            $member->roles()->attach($role);
        }

        $invitation->update(['accepted_at' => now()]);

        return $member;
    }

    public function removeMember(User $owner, User $member): void
    {
        if ($member->owner_id !== $owner->id) {
            throw new InvalidArgumentException('This user is not a member of your team.');
        }

        $member->roles()->detach();
        $member->delete();
    }

    public function listMembers(User $owner): Collection
    {
        return User::where('owner_id', $owner->id)
            ->with('roles')
            ->get();
    }

    public function listPendingInvitations(User $owner): Collection
    {
        return TeamInvitation::where('owner_id', $owner->id)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->get();
    }

    public function revokeInvitation(User $owner, TeamInvitation $invitation): void
    {
        if ($invitation->owner_id !== $owner->id) {
            throw new InvalidArgumentException('You do not own this invitation.');
        }

        $invitation->delete();
    }

    public function memberCountFor(User $owner): int
    {
        return User::where('owner_id', $owner->id)->count();
    }
}
