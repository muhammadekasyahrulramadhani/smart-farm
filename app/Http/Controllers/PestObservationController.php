<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePestObservationRequest;
use App\Models\PestObservation;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;

class PestObservationController extends Controller
{
    public function store(StorePestObservationRequest $request): RedirectResponse
    {
        $data = $request->validated();

        // Ensure bedengan belongs to the given project.
        $project = Project::findOrFail($data['project_id']);
        if (! $project->bedengans()->whereKey($data['bedengan_id'])->exists()) {
            return back()->withErrors(['bedengan_id' => 'Bedengan tidak termasuk dalam project ini.'])->withInput();
        }

        PestObservation::create([
            ...$data,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('dashboard')->with('success', 'Observasi hama berhasil disimpan.');
    }
}
