<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Models\SprayHistory;
use App\Models\PestObservation;
use App\Models\Bedengan;
use App\Models\Device;
use App\Models\DeviceActivity;
use App\Models\User;

// Halaman Login (publik)
Route::get('/login', function () {
    return view('auth.login');
})->name('login');

// Proses Login: pakai username (tanpa email)
Route::post('/login', function (Request $request) {
    $credentials = $request->validate([
        'username' => ['required', 'string', 'max:50'],
        'password' => ['required'],
    ]);

    $login = trim($credentials['username']);
    $user = User::where('username', $login)
        ->orWhere('name', $login)
        ->orWhere('email', $login)
        ->first();
    if ($user && Hash::check($credentials['password'], $user->password)) {
        session([
            'logged_in' => true,
            'username' => $user->username ?? $user->name,
            'role' => $user->role ?? 'user',
        ]);
        return redirect()->intended('dashboard');
    }

    // Fallback demo (jika seeder belum jalan)
    if ($login === 'admin' && $credentials['password'] === 'admin123') {
        session(['logged_in' => true, 'username' => 'admin', 'role' => 'admin']);
        return redirect()->intended('dashboard');
    }
    if ($login === 'user' && $credentials['password'] === 'user123') {
        session(['logged_in' => true, 'username' => 'user', 'role' => 'user']);
        return redirect()->intended('dashboard');
    }

    return back()->withErrors([
        'username' => 'Username atau password salah.',
    ])->onlyInput('username');
});

// Logout
Route::get('/logout', function (Request $request) {
    session()->forget(['logged_in', 'username', 'role']);
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect('/login');
});

// Dashboard (wajib login via middleware)
Route::middleware('auth.session')->group(function () {
    Route::get('/', function () {
        return view('dashboard', ['username' => session('username'), 'role' => session('role')]);
    });

    Route::get('/dashboard', function () {
        return view('dashboard', ['username' => session('username'), 'role' => session('role')]);
    });

    // --- Read (semua role) ---
    Route::get('/api/history', function () {
        return response()->json(SprayHistory::orderBy('date', 'desc')->get());
    });

    Route::get('/api/observations', function () {
        return response()->json(PestObservation::orderBy('date', 'asc')->get());
    });

    Route::get('/api/bedengans', function () {
        return response()->json(Bedengan::orderBy('name')->get());
    });

    // Umpan aktivitas aktuator ON/OFF dari ESP & web (semua role boleh lihat)
    Route::get('/api/device-activities', function () {
        return response()->json(
            DeviceActivity::orderBy('created_at', 'desc')->limit(50)->get()
        );
    });

    // Daftar koneksi perangkat (semua role boleh lihat)
    Route::get('/api/devices', function () {
        $threshold = (int) env('DEVICE_ONLINE_THRESHOLD', 90);
        return response()->json(
            Device::orderBy('name')->get()->map(function ($d) use ($threshold) {
                return [
                    'id' => $d->id,
                    'name' => $d->name,
                    'type' => $d->type,
                    'bedengan' => $d->bedengan,
                    'last_seen_at' => $d->last_seen_at,
                    'ip_address' => $d->ip_address,
                    'is_online' => $d->isOnline($threshold),
                ];
            })
        );
    });

    // --- Input (admin + user boleh) ---
    Route::post('/api/history/add', function (Request $request) {
        $data = $request->validate([
            'date' => 'required|date',
            'time' => 'nullable|string',
            'bedengan' => 'required|string',
            'duration' => 'nullable|integer|min:1',
            'notes' => 'nullable|string',
        ]);

        $record = SprayHistory::create([
            'date' => $data['date'],
            'time' => $data['time'] ?? '06:00',
            'bedengan' => $data['bedengan'],
            'duration' => $data['duration'] ?? 5,
            'notes' => $data['notes'] ?? '',
            'user' => session('username', 'admin'),
        ]);

        return response()->json(['success' => true, 'record' => $record]);
    });

    Route::post('/api/observations/add', function (Request $request) {
        $data = $request->validate([
            'date' => 'required|date',
            'bedengan' => 'required|string',
            'severity' => 'required|string',
            'count' => 'nullable|integer|min:0',
        ]);

        $obs = PestObservation::create([
            'date' => $data['date'],
            'bedengan' => $data['bedengan'],
            'severity' => $data['severity'],
            'count' => $data['count'] ?? 0,
            'user' => session('username', 'admin'),
        ]);

        return response()->json(['success' => true, 'observation' => $obs]);
    });

    // --- Edit milik sendiri (admin boleh edit semua) ---
    Route::post('/api/history/update/{id}', function (Request $request, $id) {
        $record = SprayHistory::findOrFail($id);
        if (session('role') !== 'admin' && $record->user !== session('username')) {
            return response()->json(['error' => 'Forbidden: hanya pemilik data / admin yang boleh edit'], 403);
        }

        $data = $request->validate([
            'date' => 'sometimes|date',
            'time' => 'nullable|string',
            'bedengan' => 'sometimes|string',
            'duration' => 'nullable|integer|min:1',
            'notes' => 'nullable|string',
        ]);

        $record->update($data);

        return response()->json(['success' => true, 'record' => $record]);
    });

    Route::post('/api/observations/update/{id}', function (Request $request, $id) {
        $obs = PestObservation::findOrFail($id);
        if (session('role') !== 'admin' && $obs->user !== session('username')) {
            return response()->json(['error' => 'Forbidden: hanya pemilik data / admin yang boleh edit'], 403);
        }

        $data = $request->validate([
            'date' => 'sometimes|date',
            'bedengan' => 'sometimes|string',
            'severity' => 'sometimes|string',
            'count' => 'nullable|integer|min:0',
        ]);

        $obs->update($data);

        return response()->json(['success' => true, 'observation' => $obs]);
    });

    // --- Khusus Admin ---
    Route::middleware('admin')->group(function () {
        Route::post('/api/history/delete/{id}', function ($id) {
            $record = SprayHistory::findOrFail($id);
            $record->delete();
            // Audit log penghapusan (bukan log perangkat)
            Log::info('Riwayat penyemprotan dihapus', [
                'id' => $id,
                'by' => session('username'),
                'role' => session('role'),
            ]);
            return response()->json(['success' => true]);
        });

        Route::post('/api/observations/delete/{id}', function ($id) {
            $obs = PestObservation::findOrFail($id);
            $obs->delete();
            Log::info('Observasi hama dihapus', [
                'id' => $id,
                'by' => session('username'),
                'role' => session('role'),
            ]);
            return response()->json(['success' => true]);
        });

        // Catat aktivitas toggle dari tombol web (khusus Admin; dipanggil toggleDevice)
        Route::post('/api/device-activities/web', function (Request $request) {
            $data = $request->validate([
                'actuator' => 'required|in:lampu,mist',
                'status' => 'required|in:on,off',
                'device_name' => 'nullable|string|max:50',
            ]);
            $deviceName = $data['device_name']
                ?? Device::where('type', $data['actuator'])->orderBy('name')->value('name')
                ?? ($data['actuator'] === 'lampu' ? 'Lampu-UV-01' : 'Mist-Maker-01');
            $device = Device::firstWhere('name', $deviceName);
            $activity = DeviceActivity::create([
                'device_name' => $deviceName,
                'actuator' => $data['actuator'],
                'status' => $data['status'],
                'source' => 'web',
                'actor' => session('username'),
                'bedengan' => $device?->bedengan,
            ]);
            return response()->json(['success' => true, 'activity' => $activity]);
        });

        // Kelola perangkat (daftar koneksi): khusus Admin
        Route::post('/api/devices/add', function (Request $request) {
            $data = $request->validate([
                'name' => 'required|string|max:50|unique:devices,name|regex:/^[A-Za-z0-9_-]+$/',
                'type' => 'required|in:lampu,mist,lain',
                'bedengan' => 'nullable|string|max:50',
            ]);
            $device = Device::create($data);
            return response()->json(['success' => true, 'device' => $device]);
        });

        Route::post('/api/devices/delete/{id}', function ($id) {
            Device::destroy($id);
            return response()->json(['success' => true]);
        });

        // Kelola bedengan
        Route::post('/api/bedengans/add', function (Request $request) {
            $data = $request->validate(['name' => 'required|string|max:50|unique:bedengans,name']);
            $bedengan = Bedengan::create(['name' => $data['name']]);
            return response()->json(['success' => true, 'bedengan' => $bedengan]);
        });

        Route::post('/api/bedengans/delete/{id}', function ($id) {
            Bedengan::destroy($id);
            return response()->json(['success' => true]);
        });

        // Kelola pengguna: list + reset password + tambah + hapus (tanpa email)
        Route::get('/api/users', function () {
            return response()->json(
                User::orderBy('username')->get(['id', 'name', 'username', 'role', 'created_at'])
            );
        });

        Route::post('/api/users/reset-password', function (Request $request) {
            $data = $request->validate([
                'username' => 'required|string|exists:users,username',
                'new_password' => 'required|string|min:6',
            ]);
            $user = User::where('username', $data['username'])->firstOrFail();
            $user->password = $data['new_password']; // auto-hash via casts
            $user->save();
            Log::info('Password user direset oleh admin', [
                'target' => $user->username,
                'by' => session('username'),
            ]);
            return response()->json(['success' => true]);
        });

        Route::post('/api/users/add', function (Request $request) {
            $data = $request->validate([
                'name' => 'required|string|max:50',
                'username' => 'required|string|max:50|unique:users,username|regex:/^[a-z0-9_]+$/i',
                'password' => 'required|string|min:6',
                'role' => 'required|in:admin,user',
            ]);
            // Email dibuat otomatis agar kolom DB tetap terisi, tapi tidak dipakai di UI
            $user = User::create([
                'name' => $data['name'],
                'username' => strtolower($data['username']),
                'email' => strtolower($data['username']).'@smartfarm.local',
                'password' => $data['password'],
                'role' => $data['role'],
            ]);
            Log::info('User baru ditambah oleh admin', [
                'target' => $user->username,
                'role' => $user->role,
                'by' => session('username'),
            ]);
            return response()->json(['success' => true, 'user' => $user->only(['id', 'name', 'username', 'role'])]);
        });

        Route::post('/api/users/delete/{id}', function ($id) {
            $target = User::findOrFail($id);
            // Cegah admin menghapus dirinya sendiri / akun admin utama
            if (($target->username ?? '') === 'admin' || $target->username === session('username')) {
                return response()->json(['error' => 'Akun admin utama / akun sendiri tidak boleh dihapus'], 403);
            }
            $target->delete();
            Log::info('User dihapus oleh admin', [
                'target' => $target->username,
                'by' => session('username'),
            ]);
            return response()->json(['success' => true]);
        });
    });
});

// Heartbeat ESP32 (tanpa sesi login; diamankan token khusus perangkat).
// Dipanggil berkala oleh ESP32 setiap mengirim data agar daftar koneksi terpantau.
Route::post('/api/devices/heartbeat', function (Request $request) {
    if ($request->input('token') !== env('DEVICE_TOKEN', 'smartfarm-iot-2026')) {
        return response()->json(['error' => 'Invalid device token'], 403);
    }
    $data = $request->validate([
        'name' => 'required|string|max:50',
        'type' => 'nullable|in:lampu,mist,lain',
        'bedengan' => 'nullable|string|max:50',
    ]);
    $device = Device::updateOrCreate(
        ['name' => $data['name']],
        [
            'type' => $data['type'] ?? 'lain',
            'bedengan' => $data['bedengan'] ?? null,
            'last_seen_at' => now(),
            'ip_address' => $request->ip(),
        ]
    );
    return response()->json(['success' => true, 'device' => $device->name]);
});

// Laporan status aktuator ON/OFF dari ESP (tanpa sesi login; token perangkat).
// Dipanggil ESP setiap aktuator berubah agar umpan aktivitas tampil real-time.
Route::post('/api/device-activities/report', function (Request $request) {
    if ($request->input('token') !== env('DEVICE_TOKEN', 'smartfarm-iot-2026')) {
        return response()->json(['error' => 'Invalid device token'], 403);
    }
    $data = $request->validate([
        'name' => 'required|string|max:50',
        'actuator' => 'required|in:lampu,mist',
        'status' => 'required|in:on,off',
        'bedengan' => 'nullable|string|max:50',
    ]);
    $device = Device::updateOrCreate(
        ['name' => $data['name']],
        [
            'last_seen_at' => now(),
            'ip_address' => $request->ip(),
            'bedengan' => $data['bedengan'] ?? Device::where('name', $data['name'])->value('bedengan'),
        ]
    );
    $activity = DeviceActivity::create([
        'device_name' => $device->name,
        'actuator' => $data['actuator'],
        'status' => $data['status'],
        'source' => 'esp',
        'bedengan' => $device->bedengan,
    ]);
    return response()->json(['success' => true, 'activity' => $activity->id]);
});
