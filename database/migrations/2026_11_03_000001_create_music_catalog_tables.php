<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracks', function (Blueprint $table) {
            $table->id();
            $table->string('path')->unique();
            $table->string('title');
            $table->string('artist')->nullable();
            $table->string('album')->nullable();
            $table->unsignedBigInteger('size');
            $table->unsignedBigInteger('file_mtime');
            $table->boolean('is_missing')->default(false);
            $table->timestamps();
        });
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('type', 16);
            $table->string('name');
            $table->string('slug');
            $table->unique(['type', 'slug']);
            $table->timestamps();
        });
        Schema::create('track_tag', function (Blueprint $table) {
            $table->foreignId('track_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->string('source')->default('manual');
            $table->primary(['track_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('track_tag');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('tracks');
    }
};
