<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_recipient_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('attempt_number');
            $table->json('request_payload_redacted')->nullable();
            $table->json('response_payload_redacted')->nullable();
            $table->string('meta_message_id')->nullable();
            $table->string('result');
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('attempted_at');
            $table->timestamps();

            $table->index(['campaign_recipient_id', 'attempt_number']);
            $table->index('meta_message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_attempts');
    }
};
