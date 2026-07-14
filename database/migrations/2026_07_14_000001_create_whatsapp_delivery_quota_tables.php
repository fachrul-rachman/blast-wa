<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_delivery_quota_states', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
        });

        Schema::create('whatsapp_delivery_quota_usages', function (Blueprint $table): void {
            $table->id();
            $table->string('phone_normalized');
            $table->foreignId('campaign_recipient_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('accepted_at');
            $table->timestamps();

            $table->index('phone_normalized');
            $table->index('accepted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_delivery_quota_usages');
        Schema::dropIfExists('whatsapp_delivery_quota_states');
    }
};
