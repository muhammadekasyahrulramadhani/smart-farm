<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Smart Farm</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 25px 16px;
            font-family: 'Poppins', sans-serif;

            background:
                linear-gradient(rgba(5, 25, 12, 0.35), rgba(5, 25, 12, 0.55)),
                url('/images/lombok.jpg') center center / cover no-repeat fixed;
        }

        .login-card {
            width: 100%;
            max-width: 480px;
            padding: 45px 40px;
            border: 1px solid rgba(255, 255, 255, 0.55);
            border-radius: 25px;

            background: rgba(255, 255, 255, 0.13);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);

            box-shadow: 0 15px 45px rgba(0, 0, 0, 0.22);
            color: #fff;
        }

        .brand {
            text-align: center;
            margin-bottom: 35px;
        }

        .brand h2 {
            font-size: 29px;
            font-weight: 700;
            margin-bottom: 8px;
            color: #fff;
        }

        .brand p {
            font-size: 13px;
            color: rgba(255, 255, 255, 0.85);
            margin: 0;
            letter-spacing: 1px;
        }

        .welcome-title {
            font-size: 25px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .welcome-subtitle {
            font-size: 13px;
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 28px;
        }

        .form-label {
            color: #fff;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 9px;
        }

        .input-wrapper {
            position: relative;
            margin-bottom: 22px;
        }

        .input-wrapper input {
            width: 100%;
            height: 57px;
            padding: 12px 48px 12px 17px;
            border: 1px solid rgba(255, 255, 255, 0.65);
            border-radius: 14px;
            outline: none;

            background: rgba(255, 255, 255, 0.09);
            color: #fff;
            font-family: inherit;
            font-size: 14px;

            transition: 0.25s;
        }

        .input-wrapper input::placeholder {
            color: rgba(255, 255, 255, 0.75);
        }

        .input-wrapper input:focus {
            border-color: #c5ed9a;
            background: rgba(255, 255, 255, 0.17);
            box-shadow: 0 0 0 3px rgba(197, 237, 154, 0.15);
        }

        .input-icon {
            position: absolute;
            right: 17px;
            top: 50%;
            transform: translateY(-50%);
            color: #fff;
            width: 22px;
            height: 22px;
            pointer-events: none;
        }

        .password-toggle {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #fff;
            cursor: pointer;
            padding: 5px;
            display: flex;
            align-items: center;
        }

        .password-toggle svg {
            width: 23px;
            height: 23px;
        }

        .btn-login {
            width: 100%;
            height: 55px;
            margin-top: 8px;
            border: 1px solid rgba(255, 255, 255, 0.6);
            border-radius: 14px;

            background: linear-gradient(135deg, #258b4d, #176b37);
            color: #fff;

            font-family: inherit;
            font-size: 16px;
            font-weight: 600;
            letter-spacing: 0.5px;

            transition: 0.25s;
        }

        .btn-login:hover {
            background: linear-gradient(135deg, #31a65f, #1b7b42);
            transform: translateY(-2px);
            box-shadow: 0 7px 20px rgba(0, 0, 0, 0.2);
        }

        .login-footer {
            text-align: center;
            margin-top: 28px;
            color: rgba(255, 255, 255, 0.75);
            font-size: 11px;
            letter-spacing: 0.5px;
        }

        .login-error {
            padding: 12px 15px;
            margin-bottom: 22px;
            border: 1px solid rgba(255, 180, 180, 0.6);
            border-radius: 12px;
            background: rgba(120, 0, 0, 0.35);
            color: #fff;
            font-size: 13px;
        }

        @media (max-width: 576px) {
            body {
                background-attachment: scroll;
            }

            .login-card {
                padding: 35px 24px;
                border-radius: 20px;
            }

            .brand h2 {
                font-size: 25px;
            }

            .welcome-title {
                font-size: 22px;
            }
        }
    </style>
</head>

<body>

    <div class="login-card">

        <div class="brand">
            <h2>Smart Farm</h2>
            <p>SMART PEST CONTROL AND MONITORING SYSTEM</p>
        </div>

        <div class="welcome-title">Welcome Back</div>
        <div class="welcome-subtitle">
            Silakan login untuk mengakses dashboard Smart Farm.
        </div>

        @if($errors->any())
            <div class="login-error">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="/login">
            @csrf

            <div class="mb-3">
                <label class="form-label">Username</label>

                <div class="input-wrapper">
                    <input
                        type="text"
                        name="username"
                        placeholder="Masukkan username"
                        value="{{ old('username') }}"
                        required
                        autofocus
                        autocomplete="username"
                    >

                    <svg class="input-icon" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round">
                        <circle cx="12" cy="8" r="4"/>
                        <path d="M4 21c0-4.4 3.6-7 8-7s8 2.6 8 7"/>
                    </svg>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Password</label>

                <div class="input-wrapper">
                    <input
                        type="password"
                        name="password"
                        id="password"
                        placeholder="Masukkan password"
                        required
                        autocomplete="current-password"
                    >

                    <button
                        type="button"
                        class="password-toggle"
                        onclick="togglePassword()"
                        aria-label="Tampilkan atau sembunyikan password"
                    >
                        <svg id="eyeIcon" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round"
                             stroke-linejoin="round">
                            <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-login">
                Login
            </button>

        </form>

        <div class="login-footer">
            Powered by Smart Farm IoT
        </div>

    </div>

    <script>
        function togglePassword() {
            const password = document.getElementById('password');
            const icon = document.getElementById('eyeIcon');

            if (password.type === 'password') {
                password.type = 'text';

                icon.innerHTML = `
                    <path d="M3 3l18 18"/>
                    <path d="M10.6 10.6a2 2 0 002.8 2.8"/>
                    <path d="M9.9 5.2A10.5 10.5 0 0112 5c6.4 0 10 7 10 7a15 15 0 01-3 3.8"/>
                    <path d="M6.2 6.2C3.5 8 2 12 2 12s3.6 7 10 7c1.3 0 2.5-.3 3.5-.7"/>
                `;
            } else {
                password.type = 'password';

                icon.innerHTML = `
                    <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7z"/>
                    <circle cx="12" cy="12" r="3"/>
                `;
            }
        }
    </script>

</body>
</html>
