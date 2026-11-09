<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tracks', function (Blueprint $table) {
            $table->index(['is_missing', 'artist', 'title'], 'tracks_is_missing_artist_title_index');
        });

        Schema::table('track_tag', function (Blueprint $table) {
            $table->index(['tag_id', 'track_id'], 'track_tag_tag_id_track_id_index');
        });

        Schema::table('metadata_suggestions', function (Blueprint $table) {
            $table->index(['provider', 'status'], 'metadata_suggestions_provider_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('tracks', function (Blueprint $table) {
            $table->dropIndex('tracks_is_missing_artist_title_index');
        });

        Schema::table('track_tag', function (Blueprint $table) {
            $table->dropIndex('track_tag_tag_id_track_id_index');
        });

        Schema::table('metadata_suggestions', function (Blueprint $table) {
            $table->dropIndex('metadata_suggestions_provider_status_index');
        });
    }
};
