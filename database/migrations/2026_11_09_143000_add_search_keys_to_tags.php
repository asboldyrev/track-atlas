<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * D3: регистронезависимый поиск по справочнику тегов.
 *
 * SQLite сравнивает LIKE без учёта регистра только для ASCII: «альт» не находил
 * тег «Альт», а «АЛЬТ» не находил ничего. lower()/LOWER() в SQLite сворачивают
 * регистр тоже только для ASCII, поэтому регистр снимается в PHP (mb_strtolower)
 * и хранится в служебных колонках `name_search`/`name_ru_search`: поиск сравнивает
 * свёрнутый запрос со свёрнутым значением. Колонки поддерживает модель `Tag`
 * (событие `saving`), наружу в JSON они не отдаются (`Tag::$hidden`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->string('name_search', 120)->nullable();
            $table->string('name_ru_search', 120)->nullable();
        });

        // Наполнение существующих строк: значения обязаны совпадать с тем, что
        // посчитала бы модель, иначе ранее созданные теги выпадут из поиска.
        // Свёртка продублирована, а не взята из Tag::searchKey(): миграция
        // должна оставаться работоспособной независимо от текущего кода модели
        // (тот же приём, что и со словарём переводов в соседней миграции).
        DB::table('tags')
            ->select('id', 'name', 'name_ru')
            ->orderBy('id')
            ->chunkById(200, function ($tags): void {
                $now = now();

                foreach ($tags as $tag) {
                    DB::table('tags')->where('id', $tag->id)->update([
                        'name_search' => self::fold($tag->name),
                        'name_ru_search' => self::fold($tag->name_ru),
                        'updated_at' => $now,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->dropColumn(['name_search', 'name_ru_search']);
        });
    }

    private static function fold(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_strtolower($value);
    }
};
