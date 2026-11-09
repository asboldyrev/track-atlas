# HTTP API

Короткая справка по эндпоинтам. Страницы отдаёт Blade-обёртка (`GET /`), данные — JSON.
Новые эндпоинты `/api/*` добавлены рядом со старыми (`/catalog`, `/tags`, `/tags/{tag}/translation`):
старые остаются рабочими до переключения фронтенда и удаляются отдельной задачей, поэтому
контракт ниже — единственный, на который стоит опираться новому коду.

## GET /api/meta

Служебные данные: лимиты и локали. Значения берутся из `config/api.php` и не дублируются на клиенте.

```json
{
  "name": "track-atlas",
  "locales": { "default": "ru", "available": ["ru"] },
  "limits": {
    "batch_tracks_max": 200,
    "ai_tracks_max": 10,
    "tracks_per_page": 30,
    "tags_per_page": 50,
    "search_max_length": 200,
    "tag_name_max_length": 80,
    "tag_name_ru_max_length": 120
  },
  "ai": { "model": "openai/gpt-4o-mini" }
}
```

- `batch_tracks_max` — максимум id треков в одном `POST /tracks/batch-tags` (то же число, что в валидации).
- `ai_tracks_max` — максимум треков в одном `POST /ai/classify`; на клиенте по нему режется пачка
  запросов к модели вместо литерала.
- `tracks_per_page` / `tags_per_page` — размер страницы списков ниже.

## GET /api/tracks

Список треков с фильтрами каталога. Ответ: `{"tracks": <пагинатор Laravel>}` —
`tracks.data`, `tracks.total`, `tracks.current_page`, `tracks.last_page`.

Параметры (те же, что у `/catalog`):

| Параметр | Значение |
| --- | --- |
| `search` | подстрока по `title` или `artist`, не длиннее `limits.search_max_length` |
| `genres[]` | id тегов типа `genre`, OR внутри группы |
| `moods[]` | id тегов типа `mood`, OR внутри группы |
| `untagged` | `genre`, `mood` или `all` — треки без меток этого типа |
| `page` | номер страницы, размер — `limits.tracks_per_page` |

Поиск устроен в два пути: запросы от 3 символов идут через полнотекстовый индекс FTS5
(`tracks_fts`, внешний контент `tracks`), поэтому они совпадают по **началу слова**
(токена), а не по произвольной подстроке: `night` найдёт `Nightcall`, но не `Midnight`.
Запросы в 1–2 символа по-прежнему используют `LIKE '%…%'`.

Треки с `is_missing = true` не отдаются. Каждая строка содержит `tags` и
`metadata_suggestions` (только `provider = openrouter`). Справочника тегов и переводов в ответе нет —
за них отвечает `/api/tags`.

Ошибки валидации — 422 с `errors` в стандартном формате Laravel.

## GET /api/tags

Справочник тегов. Ответ: `{"tags": <пагинатор Laravel>}`, размер страницы — `limits.tags_per_page`.

| Параметр | Значение |
| --- | --- |
| `type` | `genre` или `mood` |
| `search` | подстрока по `name` или `name_ru` |
| `page` | номер страницы |

Строка тега: `id`, `type`, `name`, `slug`, `name_ru`, `tracks_count`.

- `name_ru` — единственный источник русского названия. Словарь переехал из
  `config/tag_translations.php` в колонку `tags.name_ru` миграцией
  `2026_11_09_131500_move_tag_translations_to_name_ru.php`; значения, заданные вручную, не перетираются.
  `null` означает «перевода нет» — клиент показывает `name`.
- `tracks_count` — число треков с этой меткой, не считая отсутствующих файлов (`is_missing`), чтобы
  цифра совпадала с тем, что видно в `/api/tracks`.

Сортировка: `type`, затем `name`.

## Мутации

Пока остаются на старых web-маршрутах (`POST /tags`, `PUT /tags/{tag}/translation`,
`PUT /tracks/{track}/tags`, `POST /tracks/batch-tags`, `POST /ai/classify`,
`PUT /ai/suggestions/{suggestion}`), защищены CSRF-токеном страницы.
