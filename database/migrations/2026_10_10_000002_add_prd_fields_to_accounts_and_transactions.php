<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->string('currency', 3)->default('USD')->after('description');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->timestamp('date')->nullable()->after('paymentType');
            $table->string('category')->nullable()->after('date');
            $table->json('tags')->nullable()->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('currency');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['date', 'category', 'tags']);
        });
    }
};
