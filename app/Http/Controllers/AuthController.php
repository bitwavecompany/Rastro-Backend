<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Http\Requests\RegisterSyncRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\UpdateProfileRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Mail;
use App\Mail\PremiumRequestMail;

class AuthController extends Controller
{
    public function registerSync(RegisterSyncRequest $request)
    {
        DB::beginTransaction();

        try {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);

            $accountMapping = [];
            $savedAccounts = [];
            $savedTransactions = [];

            $localAccounts = $request->input('localData.accounts', []);

            if (count($localAccounts) > 0) {
                foreach ($localAccounts as $localAccount) {
                    $account = $user->accounts()->create([
                        'name' => $localAccount['name'],
                        'type' => $localAccount['type'],
                        'amount' => $localAccount['amount'],
                        'description' => $localAccount['description'] ?? null,
                    ]);
                    
                    $accountMapping[$localAccount['id']] = $account->id;
                    $savedAccounts[] = $account;
                }
            } else {
                $savedAccounts[] = $user->accounts()->create([
                    'name' => 'Efectivo',
                    'type' => 'cash',
                    'amount' => 0,
                    'description' => 'Cuenta principal por defecto'
                ]);
            }

            if ($request->has('localData.transactions')) {
                foreach ($request->input('localData.transactions') as $localTx) {
                    $realAccountUuid = $accountMapping[$localTx['accountId']] ?? null;

                    if ($realAccountUuid) {
                        $tx = $user->transactions()->create([
                            'account_id' => $realAccountUuid,
                            'kind' => $localTx['kind'],
                            'amount' => $localTx['amount'],
                            'reason' => $localTx['reason'],
                            'description' => $localTx['description'] ?? null,
                            'paymentType' => $localTx['paymentType'],
                            'deleteReason' => $localTx['deleteReason'] ?? null,
                        ]);

                        if (isset($localTx['deletedAt']) && $localTx['deletedAt']) {
                            $tx->deleted_at = $localTx['deletedAt'];
                            $tx->save();
                        }
                        $savedTransactions[] = $tx;
                    }
                }
            }

            DB::commit();

            $user->refresh();

            $token = $user->createToken('auth_token')->plainTextToken;

            return $this->createdResponse([
                'token' => $token,
                'user' => $user,
                'syncedData' => [
                    'accounts' => $savedAccounts,
                    'transactions' => $savedTransactions
                ]
            ], 'Registro y sincronización exitosos');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse(
                config('app.debug') ? $e->getMessage() : 'Error interno al sincronizar los datos', 
                500
            );
        }
    }

    public function login(LoginRequest $request)
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales son incorrectas.'],
            ]);
        }

        if ($user->accounts()->count() === 0) {
            $user->accounts()->create([
                'name' => 'Efectivo',
                'type' => 'cash',
                'amount' => 0,
                'description' => 'Cuenta principal por defecto'
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->successResponse([
            'token' => $token,
            'user' => $user
        ], 'Inicio de sesión exitoso');
    }

    public function me(Request $request)
    {
        return $this->successResponse($request->user(), 'Perfil obtenido correctamente');
    }

    public function updateMe(UpdateProfileRequest $request)
    {
        $user = $request->user();
        
        $user->update($request->validated());

        return $this->successResponse($user, 'Perfil actualizado correctamente');
    }

    public function upgradeRequest(Request $request)
    {
        $validated = $request->validate([
            'payment_method' => 'required|in:cash,transfer',
            'payment_bank' => 'required_if:payment_method,transfer|string|max:255',
            'payment_receipt' => 'required|string|max:255',
        ]);

        $user = $request->user();
        
        $user->update([
            'payment_method' => $validated['payment_method'],
            'payment_bank' => $validated['payment_bank'] ?? null,
            'payment_receipt' => $validated['payment_receipt'],
        ]);

        Mail::to('bitwave.company@gmail.com')->send(new PremiumRequestMail($user));

        return $this->successResponse($user, 'Solicitud de Premium recibida. En espera de verificación.');
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        
        return $this->successResponse(null, 'Sesión cerrada exitosamente');
    }
}