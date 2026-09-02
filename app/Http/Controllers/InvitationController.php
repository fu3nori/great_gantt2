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
use Illuminate\Validation\ValidationException;

class InvitationController extends Controller
{
    public function store(Request $request, Project $project)
    {
        $this->authorize($request->input('role') === 'pm' ? 'invitePm' : 'inviteMember', $project);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:254'],
            'role' => ['required', 'in:pm,member'],
        ], [], [
            'name' => '氏名',
            'email' => 'メールアドレス',
            'role' => 'ポジション',
        ]);
        $email = Str::lower(trim($data['email']));

        $alreadyMember = $project->members()
            ->where('status', 'active')
            ->whereHas('user', fn ($query) => $query->whereRaw('LOWER(email) = ?', [$email]))
            ->exists();
        if ($alreadyMember) {
            throw ValidationException::withMessages(['email' => 'このメールアドレスのユーザーは既にプロジェクトへ参加しています。']);
        }

        $pendingInvitation = $project->invitations()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->exists();
        if ($pendingInvitation) {
            throw ValidationException::withMessages(['email' => 'このメールアドレスには有効な招待を送信済みです。必要な場合は招待を再送してください。']);
        }

        $token = Str::random(64);
        $invitation = ProjectInvitation::create([
            'organization_id' => $project->organization_id, 'project_id' => $project->id, 'invited_by' => $request->user()->id,
            'email' => $email, 'name' => trim($data['name']), 'role' => $data['role'], 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(7),
        ]);
        Notification::route('mail', $invitation->email)->notify(new ProjectInvitationNotification($invitation, $token));

        return back()->with('success', $invitation->name.'さんへの招待メール送信を受け付けました。');
    }

    public function show(string $token)
    {
        $invitation = $this->find($token);
        $existingUser = User::whereRaw('LOWER(email) = ?', [Str::lower($invitation->email)])->first();
        if ($existingUser && ! Auth::check()) {
            session()->put('url.intended', route('invitations.show', $token));
        }

        return view('invitations.show', compact('invitation', 'token', 'existingUser'));
    }

    public function password(string $token)
    {
        $invitation = $this->find($token);
        $existingUser = User::whereRaw('LOWER(email) = ?', [Str::lower($invitation->email)])->first();

        if ($existingUser) {
            return redirect()->route('invitations.show', $token);
        }

        return view('invitations.password', compact('invitation', 'token'));
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
        $existing = User::whereRaw('LOWER(email) = ?', [Str::lower($invitation->email)])->first();
        if ($existing) {
            abort_unless($request->user()?->is($existing), 403, '招待先メールアドレスのアカウントでログインしてください。');
            $user = $existing;
        } else {
            $rules = ['password' => ['required', 'confirmed', Password::min(8)]];
            if (blank($invitation->name)) {
                $rules['name'] = ['required', 'string', 'max:255'];
            }
            $data = $request->validate($rules, [], ['name' => '氏名', 'password' => 'パスワード']);
            $user = User::create([
                'name' => filled($invitation->name) ? $invitation->name : trim($data['name']),
                'email' => $invitation->email,
                'password' => $data['password'],
                'status' => 'active',
            ]);
        }
        DB::transaction(function () use ($invitation, $user) {
            $organizationMembership = OrganizationMember::firstOrNew(['organization_id' => $invitation->organization_id, 'user_id' => $user->id]);
            $organizationMembership->fill([
                'role' => $this->higherRole($organizationMembership->role, $invitation->role, ['member', 'pm', 'owner']),
                'status' => 'active',
                'joined_at' => $organizationMembership->joined_at ?? now(),
            ])->save();
            if ($invitation->project_id) {
                $projectMembership = ProjectMember::firstOrNew(['project_id' => $invitation->project_id, 'user_id' => $user->id]);
                $projectMembership->fill([
                    'role' => $this->higherRole($projectMembership->role, $invitation->role, ['member', 'pm']),
                    'status' => 'active',
                    'joined_at' => $projectMembership->joined_at ?? now(),
                ])->save();
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

    private function higherRole(?string $current, string $invited, array $roles): string
    {
        if ($current === null) {
            return $invited;
        }

        return array_search($current, $roles, true) >= array_search($invited, $roles, true) ? $current : $invited;
    }
}
