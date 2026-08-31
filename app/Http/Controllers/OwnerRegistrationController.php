<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class OwnerRegistrationController extends Controller
{
    public function create()
    {
        return view('auth.register-owner');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'unique:users,email'],
            'organization_name' => ['required', 'string', 'max:255'], 'password' => ['required', 'confirmed', Password::min(8)],
        ]);
        $user = DB::transaction(function () use ($data) {
            $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password'], 'status' => 'active']);
            $organization = Organization::create(['name' => $data['organization_name'], 'status' => 'active']);
            $organization->members()->create(['user_id' => $user->id, 'role' => 'owner', 'status' => 'active', 'joined_at' => now()]);

            return $user;
        });
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('home')->with('success', '事業者アカウントを作成しました。');
    }
}
