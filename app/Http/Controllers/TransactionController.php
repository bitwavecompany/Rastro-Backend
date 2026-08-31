<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\UpdateTransactionRequest;
use App\Http\Requests\DestroyTransactionRequest;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
public function index(Request $request)
    {
        $transactions = $request->user()->transactions()
            ->when($request->query('kind'), function ($query, $kind) {
                $query->where('kind', $kind);
            })
            ->when($request->query('account_id'), function ($query, $accountId) {
                $query->where('account_id', $accountId);
            })
            ->withTrashed()
            ->latest()
            ->get();
        
        return $this->successResponse($transactions, 'Movimientos obtenidos correctamente');
    }

    public function store(StoreTransactionRequest $request)
    {
        $transaction = $request->user()->transactions()->create($request->validated());

        return $this->createdResponse($transaction, 'Registro guardado exitosamente');
    }

    public function update(UpdateTransactionRequest $request, string $id)
    {
        $transaction = $request->user()->transactions()->findOrFail($id);

        $transaction->update($request->validated());

        return $this->successResponse($transaction, 'Registro actualizado correctamente');
    }

    public function destroy(DestroyTransactionRequest $request, string $id)
    {
        $transaction = $request->user()->transactions()->findOrFail($id);

        $transaction->deleteReason = $request->validated('deleteReason');
        $transaction->save();
        $transaction->delete();

        return $this->successResponse($transaction, 'Registro anulado correctamente');
    }
}