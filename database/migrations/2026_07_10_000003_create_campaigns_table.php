<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('whatsapp_template_id')->constrained()->restrictOnDelete();
            $table->json('template_snapshot');
            $table->string('status')->default('draft');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('consent_confirmed_at')->nullable();
            $table->json('import_summary')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('scheduled_at');
            $table->index('created_at');
            $table->index('title');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
