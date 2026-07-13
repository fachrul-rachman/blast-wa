<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_variable_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('variable');
            $table->string('source_type');
            $table->string('source_column_key')->nullable();
            $table->string('fixed_value')->nullable();
            $table->timestamps();

            $table->unique(['campaign_id', 'variable']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_variable_mappings');
    }
};
