<?php

namespace App\Http\Controllers;

// Importamos los Form Requests
use App\Http\Requests\StoreAccountRequest;
use App\Http\Requests\UpdateAccountRequest;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function index(Request $request)
    {
        return $this->successResponse($request->user()->accounts, 'Cuentas obtenidas correctamente');
    }

    public function store(StoreAccountRequest $request)
    {
        
        $account = $request->user()->accounts()->create($request->validated());

        return $this->createdResponse($account, 'Cuenta creada exitosamente');
    }

    public function update(UpdateAccountRequest $request, string $id)
    {
        $account = $request->user()->accounts()->findOrFail($id);
        
        $account->update($request->validated());

        return $this->successResponse($account, 'Cuenta actualizada correctamente');
    }

    public function destroy(Request $request, string $id)
    {
        $account = $request->user()->accounts()->findOrFail($id);

        if ($account->transactions()->count() > 0) {
            return $this->errorResponse('No puedes eliminar una cuenta que tiene movimientos registrados.', 409); 
        }

        $account->delete();

        return $this->successResponse(null, 'Cuenta eliminada correctamente');
    }
}