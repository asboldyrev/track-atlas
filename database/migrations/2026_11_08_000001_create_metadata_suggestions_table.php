<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metadata_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('track_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 32)->default('musicbrainz');
            $table->uuid('recording_mbid')->nullable();
            $table->unsignedTinyInteger('match_score')->nullable();
            $table->json('genres');
            $table->string('status', 16)->default('pending');
            $table->text('error')->nullable();
            $table->timestamps();
            $table->unique(['track_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metadata_suggestions');
    }
};
