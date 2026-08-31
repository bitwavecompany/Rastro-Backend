<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DestroyTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'deleteReason' => 'required|string|max:255'
        ];
    }

    public function messages(): array
    {
        return [
            'deleteReason.required' => 'Debes especificar obligatoriamente el motivo por el cual estás anulando este registro.',
            'deleteReason.max' => 'El motivo de anulación no puede tener más de 255 caracteres.'
        ];
    }
}