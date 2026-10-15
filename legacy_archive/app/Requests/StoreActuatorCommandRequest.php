<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreActuatorCommandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'command' => ['required', 'string', 'in:ON,OFF'],
            'duration' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
