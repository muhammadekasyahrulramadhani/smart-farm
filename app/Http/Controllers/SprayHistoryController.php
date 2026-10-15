<?php

namespace App\Http\Controllers;

use App\Models\SprayHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SprayHistoryController extends Controller
{
    /**
     * Get all spray history records ordered by date descending.
     */
    public function index(): JsonResponse
    {
        return response()->json(SprayHistory::orderBy('date', 'desc')->get());
    }

    /**
     * Store a newly created spray history record.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date'     => ['required', 'date'],
            'time'     => ['nullable', 'string'],
            'bedengan' => ['required', 'string'],
            'duration' => ['nullable', 'integer', 'min:1'],
            'notes'    => ['nullable', 'string'],
        ]);

        $record = SprayHistory::create([
            'date'     => $data['date'],
            'time'     => $data['time'] ?? '06:00',
            'bedengan' => $data['bedengan'],
            'duration' => $data['duration'] ?? 5,
            'notes'    => $data['notes'] ?? '',
            'user'     => session('username', 'admin'),
        ]);

        return response()->json(['success' => true, 'record' => $record], 201);
    }

    /**
     * Update an existing spray history record (owner or admin).
     */
    public function update(Request $request, int|string $id): JsonResponse
    {
        $record = SprayHistory::findOrFail($id);

        if (session('role') !== 'admin' && $record->user !== session('username')) {
            return response()->json([
                'error' => 'Forbidden: hanya pemilik data / admin yang boleh edit',
            ], 403);
        }

        $data = $request->validate([
            'date'     => ['sometimes', 'date'],
            'time'     => ['nullable', 'string'],
            'bedengan' => ['sometimes', 'string'],
            'duration' => ['nullable', 'integer', 'min:1'],
            'notes'    => ['nullable', 'string'],
        ]);

        $record->update($data);

        return response()->json(['success' => true, 'record' => $record]);
    }

    /**
     * Delete a spray history record (Admin only).
     */
    public function destroy(int|string $id): JsonResponse
    {
        $record = SprayHistory::findOrFail($id);
        $record->delete();

        Log::info('Riwayat penyemprotan dihapus', [
            'id'   => $id,
            'by'   => session('username'),
            'role' => session('role'),
        ]);

        return response()->json(['success' => true]);
    }
}
