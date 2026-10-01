<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('external_ref', 100)->unique(); // nomor invoice di sistem checkout
            $table->foreignId('product_id')->constrained();
            $table->foreignId('affiliate_id')->nullable()->constrained();

            $table->string('source', 20);        // webhook | manual
            $table->string('purchase_type', 20); // subscription | one_time
            $table->string('sale_type', 20);     // new | renewal | plan_change (hanya 'new' berkomisi)
            $table->string('subscription_plan', 50)->nullable();
            $table->unsignedSmallInteger('subscription_cycle')->nullable();

            $table->string('customer_name', 150);
            $table->string('customer_email', 150)->index();

            // Semua uang: integer rupiah.
            $table->unsignedBigInteger('base_amount');          // harga kotor sebelum PPN = dasar komisi
            $table->unsignedBigInteger('discount_amount')->default(0); // hanya pelaporan
            $table->unsignedBigInteger('tax_amount')->default(0);
            $table->unsignedBigInteger('total_paid');

            $table->string('status', 20)->default('paid');
            $table->timestamp('paid_at');
            $table->unsignedBigInteger('refund_amount')->nullable();
            $table->timestamp('refunded_at')->nullable();

            // Kode tidak dikenal / affiliator non-aktif / self-referral: dicatat tanpa komisi, ditinjau admin.
            $table->boolean('needs_review')->default(false)->index();
            $table->string('review_note', 255)->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['affiliate_id', 'paid_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
