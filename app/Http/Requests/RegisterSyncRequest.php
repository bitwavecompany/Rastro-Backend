<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterSyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; 
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|unique:users',
            'password' => 'required|string|min:6',
            'localData.accounts' => 'nullable|array',
            'localData.transactions' => 'nullable|array',
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Este correo electrónico ya está registrado. Intenta iniciar sesión.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'Debes ingresar un formato de correo válido.',
            
            'name.required' => 'El nombre completo es obligatorio.',
            
            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => 'La contraseña debe tener al menos 6 caracteres.',
        ];
    }
}