@extends('layouts.app')

@section('title', 'Login - Smart Farm Cabai')

@section('content')
<div class="max-w-md mx-auto bg-white rounded shadow p-6 mt-10">
    <h2 class="text-lg font-bold mb-1">Login Smart Farm Cabai</h2>
    <p class="text-sm text-gray-500 mb-4">Masuk untuk mengelola penyemprotan &amp; perangkat IoT.</p>
    <form method="POST" action="{{ route('login.attempt') }}">
        @csrf
        <label class="block text-sm font-medium mb-1" for="email">Email</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required
               class="w-full border rounded px-3 py-2 mb-3">

        <label class="block text-sm font-medium mb-1" for="password">Password</label>
        <input id="password" type="password" name="password" required
               class="w-full border rounded px-3 py-2 mb-3">

        <label class="flex items-center gap-2 text-sm mb-4">
            <input type="checkbox" name="remember" value="1"> Ingat saya
        </label>

        <button type="submit" class="w-full bg-green-700 hover:bg-green-800 text-white font-semibold py-2 rounded">
            Login
        </button>
    </form>
</div>
@endsection
