<?php

declare(strict_types=1);

namespace App\Actions\Transactions;

use App\Models\User;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ExportTransactionsCsvAction
{
    public function handle(User $user): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="rastro_transacciones_'.now()->format('Ymd_His').'.csv"',
        ];

        return response()->stream(function () use ($user) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM para soporte en Excel
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // Encabezados
            fputcsv($handle, [
                'ID',
                'Cuenta',
                'Tipo',
                'Monto',
                'Motivo',
                'Descripción',
                'Método de Pago',
                'Fecha',
                'Estado',
                'Motivo de Eliminación',
            ]);

            $user->transactions()
                ->with('account')
                ->withTrashed()
                ->orderByDesc('date')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->chunk(200, function ($transactions) use ($handle) {
                    foreach ($transactions as $tx) {
                        fputcsv($handle, [
                            $tx->id,
                            $tx->account?->name ?? 'N/A',
                            $tx->kind?->value ?? '',
                            $tx->amount,
                            $tx->reason,
                            $tx->description ?? '',
                            $tx->paymentType ?? '',
                            $tx->date ? $tx->date->toIso8601String() : $tx->created_at->toIso8601String(),
                            $tx->deleted_at ? 'Desactivado' : 'Activo',
                            $tx->deleteReason ?? '',
                        ]);
                    }
                });

            fclose($handle);
        }, 200, $headers);
    }
}
