<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('premium_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending_verification');
            $table->string('payment_method');
            $table->string('origin_bank')->nullable();
            $table->string('transfer_receipt')->nullable();
            $table->text('payment_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignUuid('payment_verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('premium_requests');
    }
};
