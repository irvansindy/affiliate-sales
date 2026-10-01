<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained();
            $table->unsignedBigInteger('total_amount');
            $table->string('status', 20)->default('processing')->index();
            $table->timestamp('paid_at')->nullable();
            $table->string('proof_path')->nullable(); // disk privat
            $table->text('note')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['affiliate_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payouts');
    }
};
