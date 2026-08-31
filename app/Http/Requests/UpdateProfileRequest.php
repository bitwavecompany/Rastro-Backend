<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; 
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre o alias es obligatorio.',
            'name.max' => 'El nombre no puede tener más de 255 caracteres.',
        ];
    }
}