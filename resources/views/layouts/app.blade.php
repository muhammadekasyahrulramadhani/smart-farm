<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Smart Farm Cabai - Dashboard')</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
</head>
<body class="bg-green-50 text-gray-800 min-h-screen">
    <header class="bg-green-700 text-white shadow">
        <div class="max-w-7xl mx-auto px-4 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <div>
                <h1 class="text-xl font-bold">Smart Farm Cabai - Dashboard</h1>
                <p class="text-sm text-green-100">Sistem Tracking Penyemprotan Pestisida &amp; Kontrol IoT Portabel</p>
            </div>
            @auth
            <div class="flex items-center gap-3">
                <span class="text-sm">{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="bg-green-900 hover:bg-green-950 text-white text-sm px-3 py-1.5 rounded">Logout</button>
                </form>
            </div>
            @endauth
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 py-6">
        @if (session('success'))
            <div data-flash class="mb-4 bg-green-100 border border-green-400 text-green-800 px-4 py-2 rounded flex items-center justify-between gap-3 transition-opacity duration-500">
                <span>{{ session('success') }}</span>
                <button type="button" data-flash-close aria-label="Tutup notifikasi" class="font-bold text-lg leading-none hover:text-green-950">&times;</button>
            </div>
        @endif
        @if (session('error'))
            <div data-flash class="mb-4 bg-red-100 border border-red-400 text-red-800 px-4 py-2 rounded flex items-center justify-between gap-3 transition-opacity duration-500">
                <span>{{ session('error') }}</span>
                <button type="button" data-flash-close aria-label="Tutup notifikasi" class="font-bold text-lg leading-none hover:text-red-950">&times;</button>
            </div>
        @endif
        @if ($errors->any())
            <div class="mb-4 bg-red-100 border border-red-400 text-red-800 px-4 py-2 rounded">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>

    @stack('scripts')
    <script>
    document.querySelectorAll('[data-flash]').forEach(function (el) {
        var dismissed = false;
        var dismiss = function () {
            if (dismissed) return;
            dismissed = true;
            el.classList.add('opacity-0');
            setTimeout(function () { el.remove(); }, 500);
        };
        var closeBtn = el.querySelector('[data-flash-close]');
        if (closeBtn) closeBtn.addEventListener('click', dismiss);
        setTimeout(dismiss, 4000);
    });
    </script>
</body>
</html>
