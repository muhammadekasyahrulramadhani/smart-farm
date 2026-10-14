<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Smart Farm Cabai</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .login-card {
            width: 100%;
            max-width: 400px;
            padding: 30px;
            border-radius: 15px;
            background: white;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="text-center mb-4">
            <h2 class="text-success fw-bold">🌱 Smart Farm</h2>
            <p class="text-muted">Login Dashboard IoT</p>
        </div>

        @if($errors->any())
            <div class="alert alert-danger py-2">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="/login">
            @csrf
            <div class="mb-3">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control" value="admin" required autofocus autocomplete="username">
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" value="admin123" required>
            </div>
            <button type="submit" class="btn btn-success w-100 py-2">Login</button>
        </form>

        <div class="mt-4 p-3 bg-light rounded text-muted small">
            <strong>Demo Akun (2 permission, tanpa email):</strong><br>
            • Admin (kontrol IoT + input + hapus):<br><code>admin</code> / <code>admin123</code><br>
            • User (input + lihat, tanpa hapus & tanpa kontrol IoT):<br><code>user</code> / <code>user123</code>
        </div>
    </div>
</body>
</html>
