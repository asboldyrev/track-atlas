<?php

namespace Tests\Feature;

use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TagTranslationsMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = '2026_11_09_131500_move_tag_translations_to_name_ru.php';

    /**
     * Файл миграции возвращает анонимный класс-наследник Migration; в базовом классе
     * объявлены не все методы, поэтому работаем с объектом и вызываем динамически.
     */
    private function migrationInstance(): object
    {
        return require database_path('migrations/'.self::MIGRATION);
    }

    private function runMigration(string $method): void
    {
        $this->migrationInstance()->{$method}();
    }

    private function makeTag(string $type, string $name, ?string $nameRu = null): Tag
    {
        return Tag::create([
            'type' => $type,
            'name' => $name,
            'slug' => Str::slug($name),
            'name_ru' => $nameRu,
        ]);
    }

    public function test_dictionary_translations_are_copied_into_name_ru(): void
    {
        $pop = $this->makeTag('genre', 'Pop');
        $dark = $this->makeTag('mood', 'DARK');
        $unknown = $this->makeTag('genre', 'Not In Dictionary');

        $this->runMigration('up');

        $this->assertSame('Поп', $pop->fresh()->name_ru);
        $this->assertSame('Мрачный', $dark->fresh()->name_ru);
        $this->assertNull($unknown->fresh()->name_ru);

        // Повторный прогон (например, после пересоздания базы) ничего не портит.
        $this->runMigration('up');
        $this->assertSame('Поп', $pop->fresh()->name_ru);
    }

    public function test_manual_translations_are_not_overwritten(): void
    {
        $manual = $this->makeTag('genre', 'Pop', 'Мой поп');

        $this->runMigration('up');

        $this->assertSame('Мой поп', $manual->fresh()->name_ru);
    }

    public function test_rollback_clears_only_dictionary_values(): void
    {
        $translated = $this->makeTag('genre', 'Pop');
        $manual = $this->makeTag('mood', 'Dark', 'Мрачный вручную');

        $this->runMigration('up');
        $this->runMigration('down');

        $this->assertNull($translated->fresh()->name_ru);
        $this->assertSame('Мрачный вручную', $manual->fresh()->name_ru);
    }
}
