<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'type' => ['sometimes', 'required', Rule::in(['cash', 'bank'])],
            'amount' => 'sometimes|required|numeric|min:0',
            'description' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre de la cuenta no puede quedar vacío.',
            'name.max' => 'El nombre no puede tener más de 255 caracteres.',
            'type.required' => 'Debes especificar el tipo de cuenta.',
            'type.in' => 'El tipo de cuenta debe ser efectivo o banco.',
            'amount.required' => 'El saldo o monto no puede quedar vacío.',
            'amount.numeric' => 'El saldo debe ser un valor numérico.',
            'amount.min' => 'El saldo no puede ser negativo.',
            'description.max' => 'La descripción es muy larga (máximo 500 caracteres).'
        ];
    }
}