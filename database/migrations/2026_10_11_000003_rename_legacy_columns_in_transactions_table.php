<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->renameColumn('kind', 'type');
            $table->renameColumn('paymentType', 'payment_method');
            $table->renameColumn('deleteReason', 'deletion_reason');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->renameColumn('deletion_reason', 'deleteReason');
            $table->renameColumn('payment_method', 'paymentType');
            $table->renameColumn('type', 'kind');
        });
    }
};
