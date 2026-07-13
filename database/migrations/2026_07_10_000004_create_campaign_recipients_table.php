<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('source_row_number');
            $table->string('name')->nullable();
            $table->string('phone_original')->nullable();
            $table->string('phone_normalized')->nullable();
            $table->json('row_data');
            $table->string('validation_status');
            $table->json('validation_errors')->nullable();
            $table->string('duplicate_group_key')->nullable();
            $table->boolean('is_duplicate_winner')->default(false);
            $table->string('delivery_status')->default('pending');
            $table->string('meta_message_id')->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_code')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'delivery_status']);
            $table->index(['campaign_id', 'phone_normalized']);
            $table->index('meta_message_id');
            $table->index('duplicate_group_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_recipients');
    }
};
