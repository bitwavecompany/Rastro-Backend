<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function index()
    {
        $users = User::where('role', 'client')->latest()->get([
            'id', 'name', 'email', 'plan', 'created_at', 
            'payment_method', 'payment_bank', 'payment_receipt'
        ]);

        return $this->successResponse($users, 'Lista de clientes obtenida');
    }

    public function togglePlan(Request $request, string $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'plan' => ['required', Rule::in(['free', 'premium'])],
            'payment_method' => ['nullable', 'required_if:plan,premium', Rule::in(['cash', 'transfer'])],
            'payment_bank' => 'nullable|string|max:255',
            'payment_receipt' => 'nullable|string|max:255',
        ]);

        if ($validated['plan'] === 'free') {
            $validated['payment_method'] = null;
            $validated['payment_bank'] = null;
            $validated['payment_receipt'] = null;
        }

        $user->update($validated);

        return $this->successResponse($user, "Plan actualizado a {$validated['plan']} exitosamente.");
    }
}