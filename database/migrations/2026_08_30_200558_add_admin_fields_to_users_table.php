<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('client')->after('password');
            $table->enum('payment_method', ['cash', 'transfer'])->nullable()->after('plan');
            $table->string('payment_bank')->nullable()->after('payment_method');
            $table->string('payment_receipt')->nullable()->after('payment_bank');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'payment_method', 'payment_bank', 'payment_receipt']);
        });
    }
};
