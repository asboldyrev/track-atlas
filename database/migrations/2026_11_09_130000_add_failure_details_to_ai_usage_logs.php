<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_usage_logs', function (Blueprint $table) {
            // Failure diagnostics: an upstream code (http_429, timeout, ...) and
            // a short reason. Never an API key or a request/response payload.
            $table->string('error_code', 64)->nullable()->after('outcome');
            $table->string('error_message', 255)->nullable()->after('error_code');
        });
    }

    public function down(): void
    {
        Schema::table('ai_usage_logs', function (Blueprint $table) {
            $table->dropColumn(['error_code', 'error_message']);
        });
    }
};
