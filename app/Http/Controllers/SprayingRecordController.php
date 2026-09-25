<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSprayingRecordRequest;
use App\Models\Project;
use App\Models\SprayingRecord;
use Illuminate\Http\RedirectResponse;

class SprayingRecordController extends Controller
{
    public function store(StoreSprayingRecordRequest $request): RedirectResponse
    {
        $data = $request->validated();

        // Ensure bedengan belongs to the given project.
        $project = Project::findOrFail($data['project_id']);
        if (! $project->bedengans()->whereKey($data['bedengan_id'])->exists()) {
            return back()->withErrors(['bedengan_id' => 'Bedengan tidak termasuk dalam project ini.'])->withInput();
        }

        SprayingRecord::create([
            ...$data,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('dashboard')->with('success', 'Catatan penyemprotan berhasil disimpan.');
    }

    public function destroy(SprayingRecord $sprayingRecord): RedirectResponse
    {
        $sprayingRecord->delete();

        return redirect()->route('dashboard')->with('success', 'Riwayat penyemprotan berhasil dihapus.');
    }
}
