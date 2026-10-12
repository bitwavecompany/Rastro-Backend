<?php

declare(strict_types=1);

namespace App\Commands\Sync\Transactions;

use App\Constants\ApiMessage;
use App\Constants\HttpCode;
use App\Contracts\SyncCommandInterface;
use App\Enums\AccountType;
use App\Enums\PaymentMethod;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Support\DateFormatter;
use Exception;
use Illuminate\Support\Facades\Validator;

class RelocateTransactionCommand implements SyncCommandInterface
{
    public function __construct(private readonly array $payload) {}

    public function execute(User $user): void
    {
        $data = $this->payload;
        if (isset($data['destinationAccountId']) && ! isset($data['destination_account_id'])) {
            $data['destination_account_id'] = $data['destinationAccountId'];
        }
        if (isset($data['newId']) && ! isset($data['new_id'])) {
            $data['new_id'] = $data['newId'];
        }

        $validator = Validator::make($data, [
            'id' => ['required', 'uuid'],
            'new_id' => ['required', 'uuid'],
            'destination_account_id' => ['required', 'uuid'],
            'reason' => ['required', 'string', 'min:1', 'max:255'],
        ], [
            'reason.required' => ApiMessage::RELOCATE_REASON_REQUIRED,
        ]);

        if ($validator->fails()) {
            throw new Exception($validator->errors()->first(), HttpCode::UNPROCESSABLE_ENTITY);
        }

        $validated = $validator->validated();

        /** @var Transaction $originalTx */
        $originalTx = $user->transactions()->withTrashed()->findOrFail($validated['id']);

        /** @var Account|null $destinationAccount */
        $destinationAccount = $user->accounts()->withTrashed()->find($validated['destination_account_id']);

        // Regla 6: La cuenta destino debe existir y estar activa (deleted_at = null)
        if (! $destinationAccount || $destinationAccount->trashed()) {
            throw new Exception(ApiMessage::ACCOUNT_INACTIVE, HttpCode::FORBIDDEN);
        }

        // Regla 10: La cuenta destino debe ser diferente a la cuenta origen
        if ($originalTx->account_id === $destinationAccount->id) {
            throw new Exception(ApiMessage::RELOCATE_SAME_ACCOUNT, HttpCode::UNPROCESSABLE_ENTITY);
        }

        $parentAccount = $user->accounts()->withTrashed()->find($originalTx->account_id);
        $isParentDeactivated = $parentAccount?->trashed() ?? false;

        // Regla 16: Movimientos de cuentas desactivadas pueden trasladarse; de lo contrario, movimientos desactivados se rechazan
        if ($originalTx->trashed() && ! $isParentDeactivated) {
            throw new Exception(ApiMessage::TRANSACTION_ALREADY_INACTIVE, HttpCode::UNPROCESSABLE_ENTITY);
        }

        // Regla 7: Derivar automáticamente el payment_method según el tipo de cuenta destino
        $derivedPaymentMethod = ($destinationAccount->type === AccountType::Bank)
            ? PaymentMethod::Transfer
            : PaymentMethod::Cash;

        $trimmedReason = trim($validated['reason']);
        $inheritedDescription = $originalTx->description;

        if ($isParentDeactivated) {
            // Regla 16: El original en cuenta desactivada permanece desactivado y conserva deleteReason = 'cascade_account_deactivation'
            $dateStr = DateFormatter::format(now());
            $auditNote = "[Trasladado a {$destinationAccount->name} el {$dateStr}: {$trimmedReason}]";
            $originalTx->description = trim(($originalTx->description ? $originalTx->description.' ' : '').$auditNote);
            $originalTx->save();
        } else {
            // Regla 7: Desactivar original guardando el motivo formateado para trazabilidad
            $originalTx->deleteReason = "Trasladado a {$destinationAccount->name}: {$trimmedReason}";
            $originalTx->save();
            $originalTx->delete();
        }

        // Regla 7 y 16: Crear nuevo registro activo en la cuenta destino heredando campos
        $user->transactions()->create([
            'id' => $validated['new_id'],
            'account_id' => $destinationAccount->id,
            'kind' => $originalTx->kind,
            'amount' => $originalTx->amount,
            'reason' => $originalTx->reason,
            'description' => $inheritedDescription,
            'paymentType' => $derivedPaymentMethod->value,
            'date' => $originalTx->date ?? now(),
            'category' => $originalTx->category,
            'tags' => $originalTx->tags,
            'transfer_id' => null,
            'deleteReason' => null,
        ]);
    }
}
