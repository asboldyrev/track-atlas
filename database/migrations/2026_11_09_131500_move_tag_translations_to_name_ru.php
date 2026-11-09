<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Русские названия тегов переезжают из словаря в колонку
     * tags.name_ru (колонка добавлена миграцией 2026_11_08_000003).
     *
     * Словарь вшит в миграцию, а не читается из конфига: отдельный файл-словарь
     * уже удалён, а миграция обязана работать и на чистой базе.
     * Ручные значения name_ru не перетираются.
     */
    public function up(): void
    {
        $now = now();

        foreach ($this->translations() as $type => $map) {
            foreach ($map as $key => $nameRu) {
                DB::table('tags')
                    ->where('type', $type)
                    ->whereNull('name_ru')
                    ->where(fn ($query) => $query
                        ->whereRaw('lower(name) = ?', [$key])
                        ->orWhereRaw('lower(slug) = ?', [$key]))
                    ->update(['name_ru' => $nameRu, 'updated_at' => $now]);
            }
        }
    }

    /**
     * Откат очищает только значения, совпадающие со словарём: вручную заданное
     * название, равное словарному, неотличимо от перенесённого и будет очищено.
     * Колонка name_ru откатом не удаляется — она принадлежит другой миграции.
     */
    public function down(): void
    {
        $now = now();

        foreach ($this->translations() as $type => $map) {
            foreach ($map as $key => $nameRu) {
                DB::table('tags')
                    ->where('type', $type)
                    ->where('name_ru', $nameRu)
                    ->update(['name_ru' => null, 'updated_at' => $now]);
            }
        }
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function translations(): array
    {
        return [
            'genre' => [
                'pop' => 'Поп',
                'rock' => 'Рок',
                'pop rock' => 'Поп-рок',
                'alternative rock' => 'Альтернативный рок',
                'indie rock' => 'Инди-рок',
                'hard rock' => 'Хард-рок',
                'punk rock' => 'Панк-рок',
                'punk' => 'Панк',
                'metal' => 'Метал',
                'heavy metal' => 'Хеви-метал',
                'alternative metal' => 'Альтернативный метал',
                'nu metal' => 'Ню-метал',
                'metalcore' => 'Металкор',
                'post-rock' => 'Пост-рок',
                'indie' => 'Инди',
                'indie pop' => 'Инди-поп',
                'art pop' => 'Арт-поп',
                'electropop' => 'Электропоп',
                'synthpop' => 'Синти-поп',
                'synth-pop' => 'Синти-поп',
                'synthwave' => 'Синтвейв',
                'retrowave' => 'Ретровейв',
                'darkwave' => 'Дарквейв',
                'new wave' => 'Новая волна',
                'post-punk' => 'Постпанк',
                'gothic rock' => 'Готик-рок',
                'electronic' => 'Электронная музыка',
                'electronica' => 'Электроника',
                'dance' => 'Танцевальная музыка',
                'dance pop' => 'Танцевальный поп',
                'edm' => 'Электронная танцевальная музыка',
                'house' => 'Хаус',
                'deep house' => 'Дип-хаус',
                'techno' => 'Техно',
                'trance' => 'Транс',
                'dubstep' => 'Дабстеп',
                'drum and bass' => 'Драм-н-бейс',
                'drum & bass' => 'Драм-н-бейс',
                'ambient' => 'Эмбиент',
                'dark ambient' => 'Дарк-эмбиент',
                'downtempo' => 'Даунтемпо',
                'trip hop' => 'Трип-хоп',
                'trip-hop' => 'Трип-хоп',
                'hip hop' => 'Хип-хоп',
                'hip-hop' => 'Хип-хоп',
                'rap' => 'Рэп',
                'trap' => 'Трэп',
                'r&b' => 'Ритм-н-блюз',
                'rnb' => 'Ритм-н-блюз',
                'soul' => 'Соул',
                'funk' => 'Фанк',
                'disco' => 'Диско',
                'jazz' => 'Джаз',
                'blues' => 'Блюз',
                'folk' => 'Фолк',
                'indie folk' => 'Инди-фолк',
                'country' => 'Кантри',
                'classical' => 'Классическая музыка',
                'orchestral' => 'Оркестровая музыка',
                'cinematic' => 'Кинематографическая музыка',
                'soundtrack' => 'Саундтрек',
                'industrial' => 'Индастриал',
                'experimental' => 'Экспериментальная музыка',
                'grunge' => 'Гранж',
                'reggae' => 'Регги',
                'latin' => 'Латинская музыка',
                'k-pop' => 'Кей-поп',
                'j-pop' => 'Джей-поп',
                'anime' => 'Аниме-музыка',
            ],
            'mood' => [
                'dark' => 'Мрачный',
                'melancholic' => 'Меланхоличный',
                'melancholy' => 'Меланхолия',
                'sad' => 'Грустный',
                'emotional' => 'Эмоциональный',
                'energetic' => 'Энергичный',
                'fun' => 'Весёлый',
                'playful' => 'Игривый',
                'happy' => 'Радостный',
                'joyful' => 'Радостный',
                'cheerful' => 'Жизнерадостный',
                'uplifting' => 'Воодушевляющий',
                'inspiring' => 'Вдохновляющий',
                'motivational' => 'Мотивирующий',
                'epic' => 'Эпичный',
                'dramatic' => 'Драматичный',
                'cinematic' => 'Кинематографичный',
                'dreamy' => 'Мечтательный',
                'dreamlike' => 'Сновидческий',
                'mysterious' => 'Таинственный',
                'mystical' => 'Мистический',
                'romantic' => 'Романтичный',
                'sensual' => 'Чувственный',
                'passionate' => 'Страстный',
                'nostalgic' => 'Ностальгический',
                'retro' => 'Ретро',
                'calm' => 'Спокойный',
                'relaxing' => 'Расслабляющий',
                'peaceful' => 'Умиротворённый',
                'serene' => 'Безмятежный',
                'chill' => 'Расслабленный',
                'chillout' => 'Чиллаут',
                'ambient' => 'Атмосферный',
                'atmospheric' => 'Атмосферный',
                'intense' => 'Напряжённый',
                'tense' => 'Тревожно-напряжённый',
                'anxious' => 'Тревожный',
                'aggressive' => 'Агрессивный',
                'angry' => 'Злой',
                'powerful' => 'Мощный',
                'bold' => 'Дерзкий',
                'rebellious' => 'Бунтарский',
                'hopeful' => 'Полный надежды',
                'hopefulness' => 'Надежда',
                'euphoric' => 'Эйфорический',
                'ethereal' => 'Неземной',
                'haunting' => 'Завораживающе-мрачный',
                'bittersweet' => 'Горько-сладкий',
                'reflective' => 'Созерцательный',
                'introspective' => 'Задумчивый',
                'thoughtful' => 'Задумчивый',
                'lonely' => 'Одинокий',
                'somber' => 'Угрюмый',
                'moody' => 'Мрачновато-задумчивый',
                'groovy' => 'Ритмичный',
                'danceable' => 'Танцевальный',
                'upbeat' => 'Бодрый',
                'catchy' => 'Запоминающийся',
                'lighthearted' => 'Беззаботный',
                'warm' => 'Тёплый',
                'cold' => 'Холодный',
                'soft' => 'Мягкий',
                'gentle' => 'Нежный',
                'adventurous' => 'Приключенческий',
                'futuristic' => 'Футуристичный',
                'spacey' => 'Космический',
                'night drive' => 'Ночная поездка',
                'cyberpunk' => 'Киберпанк',
                'rainy night' => 'Дождливая ночь',
            ],
        ];
    }
};
