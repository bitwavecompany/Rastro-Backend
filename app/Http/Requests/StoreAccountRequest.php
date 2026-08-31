<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user->plan === 'free' && $user->accounts()->count() >= 3) {
            return false; 
        }
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'type' => ['required', Rule::in(['cash', 'bank'])],
            'amount' => 'required|numeric|min:0',
            'description' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre de la cuenta es obligatorio.',
            'name.max' => 'El nombre no puede tener más de 255 caracteres.',
            'type.required' => 'Debes especificar el tipo de cuenta.',
            'type.in' => 'El tipo de cuenta debe ser efectivo o banco.',
            'amount.required' => 'El saldo o monto es obligatorio.',
            'amount.numeric' => 'El saldo debe ser un valor numérico.',
            'amount.min' => 'El saldo inicial no puede ser negativo.',
            'description.max' => 'La descripción es muy larga (máximo 500 caracteres).'
        ];
    }
}