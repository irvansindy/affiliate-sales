<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained();
            $table->foreignId('product_id')->nullable()->constrained();
            $table->string('ip_hash', 64)->nullable(); // hash, bukan IP asli
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('clicked_at')->useCurrent();

            $table->index(['affiliate_id', 'clicked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_clicks');
    }
};
