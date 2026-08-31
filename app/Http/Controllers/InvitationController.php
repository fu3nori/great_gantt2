<?php

namespace App\Http\Controllers;

use App\Models\OrganizationMember;
use App\Models\Project;
use App\Models\ProjectInvitation;
use App\Models\ProjectMember;
use App\Models\User;
use App\Notifications\ProjectInvitationNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class InvitationController extends Controller
{
    public function store(Request $request, Project $project)
    {
        $this->authorize($request->input('role') === 'pm' ? 'invitePm' : 'inviteMember', $project);
        $data = $request->validate(['email' => ['required', 'email'], 'role' => ['required', 'in:pm,member']]);
        $token = Str::random(64);
        $invitation = ProjectInvitation::create([
            'organization_id' => $project->organization_id, 'project_id' => $project->id, 'invited_by' => $request->user()->id,
            'email' => strtolower($data['email']), 'role' => $data['role'], 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(7),
        ]);
        Notification::route('mail', $invitation->email)->notify(new ProjectInvitationNotification($invitation, $token));

        return back()->with('success', '招待メールをキューへ登録しました。開発環境では storage/logs/laravel.log も確認できます。');
    }

    public function show(string $token)
    {
        $invitation = $this->find($token);

        return view('invitations.show', compact('invitation', 'token'));
    }

    public function revoke(Request $request, Project $project, ProjectInvitation $invitation)
    {
        abort_unless($invitation->project_id === $project->id, 404);
        $this->authorize($invitation->role === 'pm' ? 'invitePm' : 'inviteMember', $project);
        $invitation->update(['revoked_at' => now()]);

        return back()->with('success', '招待を取り消しました。');
    }

    public function resend(Request $request, Project $project, ProjectInvitation $invitation)
    {
        abort_unless($invitation->project_id === $project->id, 404);
        $this->authorize($invitation->role === 'pm' ? 'invitePm' : 'inviteMember', $project);
        abort_if($invitation->accepted_at, 409, '承認済みの招待です。');
        $token = Str::random(64);
        $invitation->update(['token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(7), 'revoked_at' => null]);
        Notification::route('mail', $invitation->email)->notify(new ProjectInvitationNotification($invitation, $token));

        return back()->with('success', '招待を再送しました。');
    }

    public function accept(Request $request, string $token)
    {
        $invitation = $this->find($token);
        $existing = User::where('email', $invitation->email)->first();
        if ($existing) {
            abort_unless($request->user()?->is($existing), 403, '招待先メールアドレスのアカウントでログインしてください。');
            $user = $existing;
        } else {
            $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'password' => ['required', 'confirmed', Password::min(8)]]);
            $user = User::create(['name' => $data['name'], 'email' => $invitation->email, 'password' => $data['password'], 'status' => 'active']);
        }
        DB::transaction(function () use ($invitation, $user) {
            OrganizationMember::updateOrCreate(['organization_id' => $invitation->organization_id, 'user_id' => $user->id], ['role' => $invitation->role, 'status' => 'active', 'joined_at' => now()]);
            if ($invitation->project_id) {
                ProjectMember::updateOrCreate(['project_id' => $invitation->project_id, 'user_id' => $user->id], ['role' => $invitation->role, 'status' => 'active', 'joined_at' => now()]);
            }
            $invitation->update(['accepted_at' => now()]);
        });
        if (! Auth::check()) {
            Auth::login($user);
        }

        return redirect()->route('home')->with('success', '招待を承認しました。');
    }

    private function find(string $token): ProjectInvitation
    {
        $invitation = ProjectInvitation::with(['project', 'organization', 'inviter'])->where('token_hash', hash('sha256', $token))->firstOrFail();
        abort_unless($invitation->usable(), 410, 'この招待は期限切れ、取消済み、または使用済みです。');

        return $invitation;
    }
}
