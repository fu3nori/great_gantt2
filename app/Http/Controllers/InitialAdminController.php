<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class InitialAdminController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if ($this->isInitialized()) {
            return $this->initializedRedirect();
        }

        return view('auth.register-admin');
    }

    public function store(Request $request): RedirectResponse
    {
        if ($this->isInitialized()) {
            return $this->initializedRedirect();
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $admin = DB::transaction(function () use ($data) {
            if (User::query()->where('is_system_admin', true)->lockForUpdate()->exists()) {
                return null;
            }

            return User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'status' => 'active',
                'is_system_admin' => true,
            ]);
        });

        if (! $admin) {
            return $this->initializedRedirect();
        }

        Auth::login($admin);
        $request->session()->regenerate();

        return redirect()->route('admin.index')->with('success', 'システム管理者を登録しました。');
    }

    private function isInitialized(): bool
    {
        return User::query()->where('is_system_admin', true)->exists();
    }

    private function initializedRedirect(): RedirectResponse
    {
        return redirect()->route(Auth::check() ? 'home' : 'login');
    }
}
