<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AuthController extends Controller
{
    /**
     * Show the login view.
     */
    public function showLogin(): View
    {
        return view('auth.login');
    }

    /**
     * Process login request using username and password (without requiring email).
     */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string'],
        ]);

        $login = trim($credentials['username']);

        $user = User::where('username', $login)
            ->orWhere('name', $login)
            ->orWhere('email', $login)
            ->first();

        if ($user && Hash::check($credentials['password'], $user->password)) {
            $request->session()->regenerate();

            session([
                'logged_in' => true,
                'username'  => $user->username ?? $user->name,
                'role'      => $user->role ?? 'user',
            ]);

            return redirect()->intended('dashboard');
        }

        return back()->withErrors([
            'username' => 'Username atau password salah.',
        ])->onlyInput('username');
    }

    /**
     * Process logout request and invalidate session.
     */
    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget(['logged_in', 'username', 'role']);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
