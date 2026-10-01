<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            // unique = idempotensi: satu transaksi hanya satu komisi.
            $table->foreignId('transaction_id')->unique()->constrained();
            $table->foreignId('affiliate_id')->constrained();
            $table->unsignedBigInteger('base_amount');
            $table->decimal('rate', 5, 2); // snapshot rate saat komisi dibuat
            $table->unsignedBigInteger('amount');
            $table->string('status', 20)->default('pending');
            $table->timestamp('approvable_at')->nullable(); // paid_at + approval_delay_days
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('payout_id')->nullable()->constrained();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['affiliate_id', 'status']);
            $table->index(['status', 'approvable_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commissions');
    }
};
