<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained();
            $table->string('name', 150);
            $table->string('email', 150)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('company', 150)->nullable();
            $table->timestamps();
            $table->unique(['affiliate_id', 'id']);
            $table->index(['affiliate_id', 'name']);
        });

        Schema::create('crm_leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained();
            $table->unsignedBigInteger('contact_id');
            $table->foreignId('product_id')->constrained();
            $table->string('stage', 20)->default('new');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->foreign(['affiliate_id', 'contact_id'])->references(['affiliate_id', 'id'])->on('crm_contacts');
            $table->index(['affiliate_id', 'stage']);
            $table->index('contact_id');
        });

        Schema::create('crm_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('crm_leads');
            $table->foreignId('author_user_id')->constrained('users');
            $table->string('type', 20);
            $table->text('summary');
            $table->timestamp('occurred_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['lead_id', 'due_at']);
            $table->index(['due_at', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_activities');
        Schema::dropIfExists('crm_leads');
        Schema::dropIfExists('crm_contacts');
    }
};
