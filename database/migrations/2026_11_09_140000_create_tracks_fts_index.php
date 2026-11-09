<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * B7: полнотекстовый поиск по title/artist вместо LIKE '%…%'.
 *
 * Виртуальная таблица FTS5 — external content поверх tracks, поэтому текст не
 * дублируется: триггеры поддерживают индекс в актуальном состоянии при любых
 * вставках, обновлениях и удалениях (в том числе массовых UPDATE, которые идут
 * мимо событий Eloquent). rowid совпадает с tracks.id.
 *
 * Проект работает только на SQLite (docs/DECISIONS.md), поэтому для других
 * драйверов миграция не делает ничего и не мешает прогонам на иной СУБД.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        DB::statement(
            'CREATE VIRTUAL TABLE tracks_fts USING fts5('.
            "title, artist, content='tracks', content_rowid='id'".
            ')'
        );

        // Наполнение текущими данными (на чистой базе таблица пуста).
        DB::statement('INSERT INTO tracks_fts(rowid, title, artist) SELECT id, title, artist FROM tracks');

        DB::statement(<<<'SQL'
CREATE TRIGGER tracks_fts_ai AFTER INSERT ON tracks BEGIN
    INSERT INTO tracks_fts(rowid, title, artist) VALUES (new.id, new.title, new.artist);
END
SQL);

        DB::statement(<<<'SQL'
CREATE TRIGGER tracks_fts_ad AFTER DELETE ON tracks BEGIN
    INSERT INTO tracks_fts(tracks_fts, rowid, title, artist) VALUES ('delete', old.id, old.title, old.artist);
END
SQL);

        DB::statement(<<<'SQL'
CREATE TRIGGER tracks_fts_au AFTER UPDATE ON tracks BEGIN
    INSERT INTO tracks_fts(tracks_fts, rowid, title, artist) VALUES ('delete', old.id, old.title, old.artist);
    INSERT INTO tracks_fts(rowid, title, artist) VALUES (new.id, new.title, new.artist);
END
SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        DB::statement('DROP TRIGGER IF EXISTS tracks_fts_au');
        DB::statement('DROP TRIGGER IF EXISTS tracks_fts_ad');
        DB::statement('DROP TRIGGER IF EXISTS tracks_fts_ai');
        DB::statement('DROP TABLE IF EXISTS tracks_fts');
    }
};
