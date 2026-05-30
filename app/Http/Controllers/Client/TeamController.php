<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Services\TeamService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class TeamController extends Controller
{
    public function index(Request $request, TeamService $teamService): Response
    {
        $owner = $request->user();

        return Inertia::render('client/team', [
            'members' => $teamService->listMembers($owner),
            'invitations' => $teamService->listPendingInvitations($owner),
            'memberLimit' => $this->memberLimit($owner),
            'memberCount' => $teamService->memberCountFor($owner),
        ]);
    }

    public function invite(Request $request, TeamService $teamService): RedirectResponse
    {
        $owner = $request->user();

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::in(['agent', 'support-admin'])],
        ]);

        $limit = $this->memberLimit($owner);
        $count = $teamService->memberCountFor($owner);

        if ($limit !== null && $count >= $limit) {
            return back()->withErrors(['invite' => "Team member limit ({$limit}) reached."]);
        }

        try {
            $teamService->invite($owner, $validated['email'], $validated['role']);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['invite' => $e->getMessage()]);
        }

        return back()->with('success', 'Invitation sent to '.$validated['email'].'.');
    }

    public function revokeInvitation(Request $request, TeamInvitation $invitation, TeamService $teamService): RedirectResponse
    {
        try {
            $teamService->revokeInvitation($request->user(), $invitation);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['revoke' => $e->getMessage()]);
        }

        return back()->with('success', 'Invitation revoked.');
    }

    public function removeMember(Request $request, User $member, TeamService $teamService): RedirectResponse
    {
        try {
            $teamService->removeMember($request->user(), $member);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['remove' => $e->getMessage()]);
        }

        return back()->with('success', 'Member removed.');
    }

    public function showAccept(TeamInvitation $invitation): Response
    {
        abort_if($invitation->isExpired(), 410);
        abort_if(! $invitation->isPending(), 410);

        return Inertia::render('client/team-accept', [
            'invitation' => $invitation->only('email', 'role', 'expires_at'),
            'invitedBy' => $invitation->owner->only('name'),
        ]);
    }

    public function accept(Request $request, TeamInvitation $invitation, TeamService $teamService): RedirectResponse
    {
        abort_if($invitation->isExpired(), 410);
        abort_if(! $invitation->isPending(), 410);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        try {
            $member = $teamService->acceptInvitation($invitation, $validated['name'], $validated['password']);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['accept' => $e->getMessage()]);
        }

        auth()->login($member);

        return redirect('/dashboard')->with('success', 'Welcome to the team!');
    }

    private function memberLimit(User $owner): ?int
    {
        $limit = $owner->usageLimits()->latest()->value('team_member_limit');

        return $limit > 0 ? $limit : null;
    }
}
