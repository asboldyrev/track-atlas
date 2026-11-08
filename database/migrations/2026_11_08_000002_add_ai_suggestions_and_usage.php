<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('metadata_suggestions', function (Blueprint $table) {
            $table->json('moods')->nullable();
            $table->string('model')->nullable();
        });

        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32);
            $table->string('model');
            $table->json('track_ids');
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->decimal('cost_usd', 16, 8)->nullable();
            $table->string('outcome', 32);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
        Schema::table('metadata_suggestions', function (Blueprint $table) {
            $table->dropColumn(['moods', 'model']);
        });
    }
};
