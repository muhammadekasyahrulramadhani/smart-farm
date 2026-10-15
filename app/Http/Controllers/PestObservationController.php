<?php

namespace App\Http\Controllers;

use App\Models\PestObservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PestObservationController extends Controller
{
    /**
     * Get all pest observations ordered by date ascending.
     */
    public function index(): JsonResponse
    {
        return response()->json(PestObservation::orderBy('date', 'asc')->get());
    }

    /**
     * Store a newly created pest observation record.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date'     => ['required', 'date'],
            'bedengan' => ['required', 'string'],
            'severity' => ['required', 'string'],
            'count'    => ['nullable', 'integer', 'min:0'],
        ]);

        $obs = PestObservation::create([
            'date'     => $data['date'],
            'bedengan' => $data['bedengan'],
            'severity' => $data['severity'],
            'count'    => $data['count'] ?? 0,
            'user'     => session('username', 'admin'),
        ]);

        return response()->json(['success' => true, 'observation' => $obs], 201);
    }

    /**
     * Update an existing pest observation record (owner or admin).
     */
    public function update(Request $request, int|string $id): JsonResponse
    {
        $obs = PestObservation::findOrFail($id);

        if (session('role') !== 'admin' && $obs->user !== session('username')) {
            return response()->json([
                'error' => 'Forbidden: hanya pemilik data / admin yang boleh edit',
            ], 403);
        }

        $data = $request->validate([
            'date'     => ['sometimes', 'date'],
            'bedengan' => ['sometimes', 'string'],
            'severity' => ['sometimes', 'string'],
            'count'    => ['nullable', 'integer', 'min:0'],
        ]);

        $obs->update($data);

        return response()->json(['success' => true, 'observation' => $obs]);
    }

    /**
     * Delete a pest observation record (Admin only).
     */
    public function destroy(int|string $id): JsonResponse
    {
        $obs = PestObservation::findOrFail($id);
        $obs->delete();

        Log::info('Observasi hama dihapus', [
            'id'   => $id,
            'by'   => session('username'),
            'role' => session('role'),
        ]);

        return response()->json(['success' => true]);
    }
}
