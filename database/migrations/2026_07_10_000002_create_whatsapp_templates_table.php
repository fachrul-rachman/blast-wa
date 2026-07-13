<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_templates', function (Blueprint $table) {
            $table->id();
            $table->string('meta_template_id')->unique();
            $table->string('name');
            $table->string('language_code');
            $table->string('category')->nullable();
            $table->string('status');
            $table->text('body_text')->nullable();
            $table->json('body_variables');
            $table->json('components');
            $table->boolean('is_supported')->default(false);
            $table->boolean('is_available')->default(false);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('is_available');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_templates');
    }
};
