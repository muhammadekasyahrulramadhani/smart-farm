<?php

namespace App\Http\Controllers;

use App\Models\PestObservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class PestObservationController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            PestObservation::orderBy('date', 'asc')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'bedengan' => ['required', 'string'],
            'pest_type' => [
                'required',
                Rule::in(['Kutu Daun', 'Kutu Kebul', 'Gejala Jamur', 'Lainnya']),
            ],
            'severity' => [
                'required',
                Rule::in(['Ringan', 'Sedang', 'Berat']),
            ],
            'affected_plants' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $obs = PestObservation::create([
            'date' => $data['date'],
            'bedengan' => $data['bedengan'],
            'pest_type' => $data['pest_type'],
            'severity' => $data['severity'],
            'affected_plants' => $data['affected_plants'],
            'notes' => $data['notes'] ?? null,
            'count' => 0,
            'user' => session('username', 'admin'),
        ]);

        return response()->json([
            'success' => true,
            'observation' => $obs,
        ], 201);
    }

    public function update(Request $request, int|string $id): JsonResponse
    {
        $obs = PestObservation::findOrFail($id);

        if (session('role') !== 'admin' && $obs->user !== session('username')) {
            return response()->json([
                'error' => 'Forbidden: hanya pemilik data / admin yang boleh edit',
            ], 403);
        }

        $data = $request->validate([
            'date' => ['sometimes', 'date'],
            'bedengan' => ['sometimes', 'string'],
            'pest_type' => [
                'sometimes',
                Rule::in(['Kutu Daun', 'Kutu Kebul', 'Gejala Jamur', 'Lainnya']),
            ],
            'severity' => [
                'sometimes',
                Rule::in(['Ringan', 'Sedang', 'Berat']),
            ],
            'affected_plants' => ['sometimes', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $obs->update($data);

        return response()->json([
            'success' => true,
            'observation' => $obs,
        ]);
    }

    public function destroy(int|string $id): JsonResponse
    {
        $obs = PestObservation::findOrFail($id);
        $obs->delete();

        Log::info('Observasi hama dihapus', [
            'id' => $id,
            'by' => session('username'),
            'role' => session('role'),
        ]);

        return response()->json(['success' => true]);
    }
}
