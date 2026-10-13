<?php

declare(strict_types=1);

use App\Enums\TransactionCategory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Asignar categoría 'otros' a todos los gastos existentes que no tengan categoría asignada
        DB::table('transactions')
            ->where('type', 'expense')
            ->whereNull('category')
            ->update([
                'category' => TransactionCategory::Other->value,
            ]);
    }

    public function down(): void
    {
        // Revertir a null los que hayan sido fijados con 'otros' si fuera necesario
    }
};
