import { computed, ref } from 'vue'
import { getJson, mutate } from './useApi'

// A8: сортировка и выбор размера страницы.
//
// GET /api/tracks отдаёт фиксированные 30 строк и сортирует по artist+title,
// поэтому «размер страницы» и пользовательская сортировка собираются на клиенте:
// при отклонении от серверного порядка страница вычитывает нужный диапазон
// целиком (постранично, с предохранителем MAX_PAGES) и режет его сама.
// Пока настройки совпадают с серверными, работает обычный серверный режим —
// поведение по умолчанию не меняется.
export const PAGE_SIZES = [10, 20, 30, 50, 100]

export const SORT_OPTIONS = [
  { value: 'artist', label: 'Исполнитель' },
  { value: 'title', label: 'Название' },
  { value: 'album', label: 'Альбом' },
]

const SERVER_PAGE_SIZE = 30
const MAX_PAGES = 30 // не более 30 запросов при сборке полного списка

// Сколько строк реально попадает в клиентский список при сборке полного набора.
// Используется в предупреждениях интерфейса, чтобы текст не разъезжался с
// константами пагинации.
export const TRUNCATED_LIMIT = MAX_PAGES * SERVER_PAGE_SIZE

// Мутации треков живут на web-маршрутах (CSRF), но описаны здесь рядом с
// чтением: компонентам не нужно знать URL и форму запроса.
export function saveTrackTags(trackId, tagIds) {
  return mutate('/tracks/' + trackId + '/tags', 'PUT', { tags: tagIds })
}

export function batchTags(trackIds, tagIds, mode) {
  return mutate('/tracks/batch-tags', 'POST', { tracks: trackIds, tags: tagIds, mode })
}

function withPage(query, page) {
  const params = new URLSearchParams(query)
  if (page > 1) params.set('page', String(page))
  else params.delete('page')
  return params.toString()
}

function sortKey(track, key) {
  const value = track[key]
  return (value === null || value === undefined ? '' : String(value)).toLocaleLowerCase('ru')
}

async function fetchTracks(query) {
  return getJson('/api/tracks' + (query ? '?' + query : ''))
}

export function useTracks(buildFilterQuery, initial = {}) {
  const perPage = ref(initial.perPage || SERVER_PAGE_SIZE)
  const sort = ref(initial.sort || 'artist')
  const dir = ref(initial.dir || 'asc')
  const page = ref(initial.page || 1)

  const rows = ref([])
  const total = ref(0)
  const lastPage = ref(1)
  const loading = ref(false)
  const error = ref('')
  const truncated = ref(false)

  let buffer = null
  let bufferKey = ''
  let requestNumber = 0

  const clientMode = computed(() => perPage.value !== SERVER_PAGE_SIZE || sort.value !== 'artist' || dir.value !== 'asc')
  const pagination = computed(() => ({ total: total.value, current_page: page.value, last_page: lastPage.value }))

  function sortList(list) {
    const sign = dir.value === 'desc' ? -1 : 1
    const key = sort.value
    return [...list].sort((a, b) => {
      const cmp = sortKey(a, key).localeCompare(sortKey(b, key), 'ru')
      if (cmp !== 0) return sign * cmp
      return sortKey(a, 'title').localeCompare(sortKey(b, 'title'), 'ru')
    })
  }

  function invalidate() {
    buffer = null
    bufferKey = ''
  }

  async function load() {
    const current = ++requestNumber
    loading.value = true
    error.value = ''
    try {
      const filter = buildFilterQuery()
      if (!clientMode.value) {
        invalidate()
        const data = await fetchTracks(withPage(filter, page.value))
        if (current !== requestNumber) return
        // После смены фильтра текущая страница может оказаться за пределами
        // результата — тогда возвращаемся на последнюю существующую.
        if (data.tracks.last_page > 0 && page.value > data.tracks.last_page) {
          page.value = data.tracks.last_page
          return load()
        }
        rows.value = data.tracks.data
        total.value = data.tracks.total
        lastPage.value = data.tracks.last_page
        truncated.value = false
        return
      }

      const key = filter + '|' + sort.value + '|' + dir.value
      if (buffer === null || bufferKey !== key) {
        const first = await fetchTracks(withPage(filter, 1))
        if (current !== requestNumber) return
        const collected = [...first.tracks.data]
        total.value = first.tracks.total
        const last = first.tracks.last_page
        const limit = Math.min(last, MAX_PAGES)
        // Остальные страницы догружаются пачками — иначе смена сортировки
        // на большой библиотеке превращается в длинную последовательность запросов.
        const pages = []
        for (let p = 2; p <= limit; p += 1) pages.push(p)
        const CONCURRENCY = 6
        for (let i = 0; i < pages.length; i += CONCURRENCY) {
          const chunk = pages.slice(i, i + CONCURRENCY)
          const results = await Promise.all(chunk.map(p => fetchTracks(withPage(filter, p))))
          if (current !== requestNumber) return
          for (const result of results) collected.push(...result.tracks.data)
        }
        truncated.value = last > MAX_PAGES
        buffer = sortList(collected)
        bufferKey = key
      }

      if (current !== requestNumber) return
      lastPage.value = Math.max(1, Math.ceil(buffer.length / perPage.value))
      if (page.value > lastPage.value) page.value = lastPage.value
      const start = (page.value - 1) * perPage.value
      rows.value = buffer.slice(start, start + perPage.value)
    } catch (e) {
      if (current === requestNumber) error.value = e.message
    } finally {
      if (current === requestNumber) loading.value = false
    }
  }

  // Меняет страницу, если она существует; возвращает признак успеха, чтобы
  // вызывающий код мог синхронизировать URL и прокрутку только при переходе.
  function goToPage(next) {
    if (next < 1 || next > lastPage.value || loading.value) return false
    page.value = next
    return true
  }

  return {
    perPage, sort, dir, page,
    rows, total, lastPage, loading, error, truncated, clientMode, pagination,
    load, goToPage, invalidate,
  }
}
