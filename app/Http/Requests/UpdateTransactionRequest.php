<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_id' => [
                'sometimes', 'required', 'uuid', 
                Rule::exists('accounts', 'id')->where('user_id', $this->user()->id)
            ],
            'amount' => 'sometimes|required|numeric|min:0.01',
            'reason' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:500',
            'paymentType' => ['sometimes', 'required', Rule::in(['cash', 'transfer'])],
        ];
    }

    public function messages(): array
    {
        return [
            'account_id.required' => 'Debes seleccionar una cuenta válida.',
            'account_id.exists' => 'La cuenta seleccionada no existe o no te pertenece.',
            'amount.required' => 'El monto no puede quedar vacío.',
            'amount.numeric' => 'El monto debe ser numérico.',
            'amount.min' => 'El monto debe ser mayor a cero.',
            'reason.required' => 'El motivo no puede quedar vacío.',
            'reason.max' => 'El motivo es muy largo.',
            'paymentType.required' => 'El método de pago es obligatorio.',
            'paymentType.in' => 'El método de pago no es válido.',
            'description.max' => 'La descripción es muy larga.'
        ];
    }
}