<?php

declare(strict_types=1);

namespace App\Commands\Sync\Transactions;

use App\Constants\ApiMessage;
use App\Constants\HttpCode;
use App\Contracts\SyncCommandInterface;
use App\Enums\AccountType;
use App\Enums\PaymentMethod;
use App\Enums\TransactionKind;
use App\Models\Account;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CreateTransactionCommand implements SyncCommandInterface
{
    public function __construct(private readonly array $payload) {}

    public function execute(User $user): void
    {
        // Normalizar alias de campos (PRD type -> kind, payment_method -> paymentType)
        $data = $this->payload;
        if (isset($data['type']) && ! isset($data['kind'])) {
            $data['kind'] = $data['type'];
        }
        if (isset($data['payment_method']) && ! isset($data['paymentType'])) {
            $data['paymentType'] = $data['payment_method'];
        }

        $validator = Validator::make($data, [
            'id' => ['required', 'uuid'],
            'account_id' => ['required', 'uuid'],
            'kind' => ['required', Rule::enum(TransactionKind::class)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'paymentType' => ['required', Rule::enum(PaymentMethod::class)],
            'date' => ['nullable', 'date'],
            'category' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'array'],
            'transfer_id' => ['nullable', 'uuid'],
        ]);

        if ($validator->fails()) {
            throw new Exception($validator->errors()->first(), HttpCode::UNPROCESSABLE_ENTITY);
        }

        $validated = $validator->validated();

        /** @var Account|null $account */
        $account = $user->accounts()->withTrashed()->find($validated['account_id']);

        // Regla 6: La cuenta seleccionada debe existir y estar activa (deleted_at = null)
        if (! $account || $account->trashed()) {
            throw new Exception(ApiMessage::ACCOUNT_INACTIVE, HttpCode::FORBIDDEN);
        }

        // Regla 6: El tipo de la cuenta debe coincidir con el payment_method seleccionado
        $isCashMatch = ($validated['paymentType'] === PaymentMethod::Cash->value && $account->type === AccountType::Cash);
        $isTransferMatch = ($validated['paymentType'] === PaymentMethod::Transfer->value && $account->type === AccountType::Bank);

        if (! $isCashMatch && ! $isTransferMatch) {
            throw new Exception(ApiMessage::ACCOUNT_TYPE_MISMATCH, HttpCode::FORBIDDEN);
        }

        // Tratamiento de fecha (PRD 4.4): asignar hora del servidor si se envía solo YYYY-MM-DD
        if (! empty($validated['date'])) {
            $dateString = trim((string) ($data['date'] ?? ''));
            $parsedDate = Carbon::parse($validated['date']);
            if (strlen($dateString) <= 10) {
                $validated['date'] = $parsedDate->setTime(now()->hour, now()->minute, now()->second);
            } else {
                $validated['date'] = $parsedDate;
            }
        } else {
            $validated['date'] = now();
        }

        $user->transactions()->create($validated);
    }
}
