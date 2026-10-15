<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display the main smart farm dashboard.
     */
    public function index(): View
    {
        return view('dashboard', [
            'username' => session('username', 'admin'),
            'role'     => session('role', 'user'),
        ]);
    }
}
