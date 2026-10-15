<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    /**
     * Get list of users (Admin only).
     */
    public function index(): JsonResponse
    {
        $users = User::orderBy('username')->get(['id', 'name', 'username', 'role', 'created_at']);

        return response()->json($users);
    }

    /**
     * Add a new user with unique username and role (Admin only).
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:50'],
            'username' => ['required', 'string', 'max:50', 'unique:users,username', 'regex:/^[a-z0-9_]+$/i'],
            'password' => ['required', 'string', 'min:6'],
            'role'     => ['required', 'in:admin,user'],
        ]);

        $username = strtolower($data['username']);

        $user = User::create([
            'name'     => $data['name'],
            'username' => $username,
            'email'    => $username . '@smartfarm.local',
            'password' => $data['password'],
            'role'     => $data['role'],
        ]);

        Log::info('User baru ditambah oleh admin', [
            'target' => $user->username,
            'role'   => $user->role,
            'by'     => session('username'),
        ]);

        return response()->json([
            'success' => true,
            'user'    => $user->only(['id', 'name', 'username', 'role']),
        ], 201);
    }

    /**
     * Reset a user's password (Admin only).
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username'     => ['required', 'string', 'exists:users,username'],
            'new_password' => ['required', 'string', 'min:6'],
        ]);

        $user = User::where('username', $data['username'])->firstOrFail();
        $user->password = $data['new_password']; // auto-hashed via casts
        $user->save();

        Log::info('Password user direset oleh admin', [
            'target' => $user->username,
            'by'     => session('username'),
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Delete a user, with safeguards protecting primary admin and self (Admin only).
     */
    public function destroy(int|string $id): JsonResponse
    {
        $target = User::findOrFail($id);

        if (($target->username ?? '') === 'admin' || $target->username === session('username')) {
            return response()->json([
                'error' => 'Akun admin utama / akun sendiri tidak boleh dihapus',
            ], 403);
        }

        $target->delete();

        Log::info('User dihapus oleh admin', [
            'target' => $target->username,
            'by'     => session('username'),
        ]);

        return response()->json(['success' => true]);
    }
}
