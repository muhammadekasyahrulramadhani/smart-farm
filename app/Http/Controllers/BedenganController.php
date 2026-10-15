<?php

namespace App\Http\Controllers;

use App\Models\Bedengan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BedenganController extends Controller
{
    /**
     * Get all bedengan records ordered by name.
     */
    public function index(): JsonResponse
    {
        return response()->json(Bedengan::orderBy('name')->get());
    }

    /**
     * Add a new bedengan (Admin only).
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50', 'unique:bedengans,name'],
        ]);

        $bedengan = Bedengan::create(['name' => $data['name']]);

        return response()->json(['success' => true, 'bedengan' => $bedengan], 201);
    }

    /**
     * Delete a bedengan (Admin only).
     */
    public function destroy(int|string $id): JsonResponse
    {
        Bedengan::destroy($id);

        return response()->json(['success' => true]);
    }
}
