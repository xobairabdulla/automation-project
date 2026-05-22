<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Services\TeamService;
use App\Services\UsageLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use InvalidArgumentException;

class TeamController extends Controller
{
    public function index(Request $request, TeamService $teamService): \Inertia\Response
    {
        $owner = $request->user();

        return Inertia::render('client/team', [
            'members' => $teamService->listMembers($owner),
            'invitations' => $teamService->listPendingInvitations($owner),
            'memberLimit' => $this->memberLimit($owner),
            'memberCount' => $teamService->memberCountFor($owner),
        ]);
    }

    public function invite(Request $request, TeamService $teamService, UsageLimitService $usageLimitService): JsonResponse
    {
        $owner = $request->user();

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::in(['agent', 'support-admin'])],
        ]);

        $limit = $this->memberLimit($owner);
        $count = $teamService->memberCountFor($owner);

        if ($limit !== null && $count >= $limit) {
            return response()->json(['message' => "Team member limit ({$limit}) reached."], 422);
        }

        try {
            $invitation = $teamService->invite($owner, $validated['email'], $validated['role']);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($invitation, 201);
    }

    public function revokeInvitation(Request $request, TeamInvitation $invitation, TeamService $teamService): JsonResponse
    {
        try {
            $teamService->revokeInvitation($request->user(), $invitation);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        return response()->json(['ok' => true]);
    }

    public function removeMember(Request $request, User $member, TeamService $teamService): JsonResponse
    {
        try {
            $teamService->removeMember($request->user(), $member);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true]);
    }

    public function showAccept(TeamInvitation $invitation): \Inertia\Response
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
