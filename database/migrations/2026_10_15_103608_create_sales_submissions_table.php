<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sales_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('transaction_id')->nullable()->unique()->constrained();
            $table->string('purchase_type', 20);
            $table->string('customer_name', 150);
            $table->string('customer_email', 150);
            $table->string('claimed_ref', 100)->nullable();
            $table->string('proof_path');
            $table->string('status', 20)->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['affiliate_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_submissions');
    }
};
