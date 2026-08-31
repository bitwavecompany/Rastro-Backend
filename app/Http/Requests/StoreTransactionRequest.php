<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user->plan === 'free' && $user->transactions()->count() >= 50) {
            return false; 
        }
        return true;
    }

    public function rules(): array
    {
        return [
            'account_id' => [
                'required', 'uuid', 
                Rule::exists('accounts', 'id')->where('user_id', $this->user()->id)
            ],
            'kind' => ['required', Rule::in(['expense', 'income'])],
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'paymentType' => ['required', Rule::in(['cash', 'transfer'])],
        ];
    }

    public function messages(): array
    {
        return [
            'account_id.required' => 'Debes seleccionar una cuenta.',
            'account_id.uuid' => 'El identificador de la cuenta no es válido.',
            'account_id.exists' => 'La cuenta seleccionada no existe o no te pertenece.',
            
            'kind.required' => 'Debes especificar si es un ingreso o un gasto.',
            'kind.in' => 'El tipo de movimiento es inválido.',
            
            'amount.required' => 'El monto del movimiento es obligatorio.',
            'amount.numeric' => 'El monto debe ser un número válido.',
            'amount.min' => 'El monto debe ser mayor a cero.',
            
            'reason.required' => 'La categoría o motivo es obligatorio.',
            'reason.max' => 'El motivo no puede exceder los 255 caracteres.',
            
            'paymentType.required' => 'Debes seleccionar un método de pago.',
            'paymentType.in' => 'El método de pago debe ser efectivo o transferencia.',
            
            'description.max' => 'La nota opcional es muy larga (máximo 500 caracteres).'
        ];
    }
}