<?php

namespace App\Http\Requests;

use App\Enums\AttackLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StorePestObservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'project_id' => ['required', 'exists:projects,id'],
            'bedengan_id' => ['required', 'exists:bedengans,id'],
            'observation_date' => ['required', 'date'],
            'attack_level' => ['required', new Enum(AttackLevel::class)],
            'insect_count' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
