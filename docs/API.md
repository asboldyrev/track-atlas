# HTTP API

Короткая справка по эндпоинтам. Страницы отдаёт Blade-обёртка (`GET /`), данные — JSON.
Чтение каталога живёт только под `/api/*`: старый web-маршрут чтения удалён вместе с фронтендом,
переключённым на новый контракт. Мутации остаются на web-маршрутах под CSRF-защитой страницы
(см. раздел ниже).

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
    "tracks_per_page_options": [10, 20, 30, 50, 100],
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
- `tracks_per_page` / `tags_per_page` — размер страницы списков ниже (значение по умолчанию).
- `tracks_per_page_options` — допустимые значения `per_page` для `/api/tracks`; клиент берёт список
  отсюда, а не хардкодит его.

## GET /api/tracks

Список треков с фильтрами каталога. Ответ: `{"tracks": <пагинатор Laravel>}` —
`tracks.data`, `tracks.total`, `tracks.current_page`, `tracks.last_page`.

Параметры:

| Параметр | Значение |
| --- | --- |
| `search` | подстрока по `title` или `artist`, не длиннее `limits.search_max_length` |
| `genres[]` | id тегов типа `genre`, OR внутри группы |
| `moods[]` | id тегов типа `mood`, OR внутри группы |
| `untagged` | `genre`, `mood` или `all` — треки без меток этого типа |
| `page` | номер страницы (по умолчанию 1) |
| `sort` | `artist` (по умолчанию), `title` или `album` |
| `dir` | `asc` (по умолчанию) или `desc` |
| `per_page` | `10`, `20`, `30` (по умолчанию), `50` или `100` — только из `limits.tracks_per_page_options` |

Порядок строк: `<sort> <dir>`, затем `artist asc`, `title asc`, `tracks.id asc`. Тай-брейк
обязателен, поэтому пагинация не повторяет и не пропускает строки; колонка основной сортировки
в тай-брейке не дублируется. Без параметров порядок прежний (`artist asc, title asc, tracks.id asc`).
Для 2000 треков при `per_page=100` будет `last_page=20`.

`per_page` и `sort` — белые списки из `config/api.php`; произвольные числа и имена колонок
отклоняются (`422`). Значения `sort`, `dir`, `per_page` вне списка — `422` с `errors`.
`page` больше `last_page` даёт `200` и пустой `data`.

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
| `search` | подстрока по `name` или `name_ru`; регистр не важен, в том числе для кириллицы |
| `page` | номер страницы |

Строка тега: `id`, `type`, `name`, `slug`, `name_ru`, `tracks_count`.

Поиск идёт не по самим `name`/`name_ru`, а по их свёрнутым копиям `name_search`/`name_ru_search`
(нижний регистр, снятый в PHP): SQLite сравнивает `LIKE` без учёта регистра только для ASCII,
поэтому `альт`, `Альт` и `АЛЬТ` без свёртки давали разные ответы. Служебные колонки в ответ не
попадают.

- `name_ru` — единственный источник русского названия. Словарь переехал в колонку `tags.name_ru`
  миграцией `2026_11_09_131500_move_tag_translations_to_name_ru.php` из отдельного файла-словаря,
  который после переноса удалён; значения, заданные вручную, не перетираются.
  `null` означает «перевода нет» — клиент показывает `name`.
- `tracks_count` — число треков с этой меткой, не считая отсутствующих файлов (`is_missing`), чтобы
  цифра совпадала с тем, что видно в `/api/tracks`.

Сортировка: `type`, затем `name`.

## Мутации

Пока остаются на старых web-маршрутах (`POST /tags`, `PUT /tags/{tag}/translation`,
`PUT /tracks/{track}/tags`, `POST /tracks/batch-tags`, `POST /ai/classify`,
`PUT /ai/suggestions/{suggestion}`), защищены CSRF-токеном страницы.
