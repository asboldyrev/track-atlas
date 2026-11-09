# Implementation status

## 2026-11-03: Phase 1 foundation (working branch)
Implemented in the catalog-foundation feature branch: SQLite migrations, Track/Tag models, recursive Artisan scanner, basic filename-based metadata fallback, server-rendered filtering UI, category creation/editing, and initial feature tests.

**Important limitations:** This is the **first vertical slice**, not the completed planned Vue MVP. Reading rich MP3/FLAC/M4A/OGG metadata through getID3 and Vue 3 frontend will follow. The current scanner derives artist/title from `Artist - Title.ext` and does not import embedded genre metadata. Batch tag editing is not included yet. Do not mark Phase 1 complete until those are implemented.

Local instructions after checkout:
1. `composer install`
2. `cp .env.example .env` (if necessary), `php artisan key:generate`
3. Configure `DB_CONNECTION=sqlite` and `MUSIC_LIBRARY_PATH=/absolute/path/to/music` in `.env`.
4. `touch database/database.sqlite && php artisan migrate`
5. `php artisan music:scan` (or `php artisan music:scan "/path/to/music"`)
6. `php artisan serve` and open localhost:8000.
7. `php artisan test --filter=MusicCatalogTest`.

The scanner does not modify audio files. Scan only a trusted local folder. No external API or Docker is used.
