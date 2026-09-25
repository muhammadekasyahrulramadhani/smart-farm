<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'project_id' => ['required', 'exists:projects,id'],
            'device_id' => ['required', 'string', 'max:255', 'unique:devices,device_id'],
            'name' => ['required', 'string', 'max:255'],
            'firmware_version' => ['nullable', 'string', 'max:50'],
        ];
    }
}
