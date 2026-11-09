import { computed, ref } from 'vue'
import { getJson, mutate } from './useApi'

// Список и «размер страницы» — серверные: сортировка (sort/dir) и per_page
// уходят в GET /api/tracks, сервер сам режет страницу и отдаёт пагинатор
// (data/current_page/last_page/total). Клиентского буфера и сборки всего
// списка постранично больше нет — раньше она упиралась в потолок и прятала
// часть библиотеки за баннером.

export const SORT_OPTIONS = [
  { value: 'artist', label: 'Исполнитель' },
  { value: 'title', label: 'Название' },
  { value: 'album', label: 'Альбом' },
]

// Запасной список размеров страницы. Истинный источник — GET /api/meta
// (limits.tracks_per_page_options); запасной нужен лишь до того, как мета
// придёт, чтобы проверка параметра из URL не зависела от гонки запросов.
// Значения совпадают с config/api.php.
const DEFAULT_PAGE_SIZES = [10, 20, 30, 50, 100]

// Размер страницы по умолчанию (config/api.php: limits.tracks_per_page).
export const DEFAULT_PAGE_SIZE = 30

// Реактивный белый список размеров страницы. Заполняется loadMeta().
export const PAGE_SIZES = ref([...DEFAULT_PAGE_SIZES])

let metaPromise = null

// Читает служебные лимиты из /api/meta и обновляет список размеров страницы.
// Запрос один на приложение: повторные вызовы переиспользуют промис.
export function loadMeta() {
  if (!metaPromise) {
    metaPromise = getJson('/api/meta')
      .then(meta => {
        const options = meta?.limits?.tracks_per_page_options
        if (Array.isArray(options) && options.length) PAGE_SIZES.value = options.map(Number)
        return meta
      })
      .catch(() => {
        // Мета недоступна — работаем на запасном списке; повторную попытку
        // сделает следующий вызов loadMeta().
        metaPromise = null
      })
  }
  return metaPromise
}

// Мутации треков живут на web-маршрутах (CSRF), но описаны здесь рядом с
// чтением: компонентам не нужно знать URL и форму запроса.
export function saveTrackTags(trackId, tagIds) {
  return mutate('/tracks/' + trackId + '/tags', 'PUT', { tags: tagIds })
}

export function batchTags(trackIds, tagIds, mode) {
  return mutate('/tracks/batch-tags', 'POST', { tracks: trackIds, tags: tagIds, mode })
}

export function useTracks(buildFilterQuery, initial = {}) {
  const perPage = ref(initial.perPage || DEFAULT_PAGE_SIZE)
  const sort = ref(initial.sort || 'artist')
  const dir = ref(initial.dir || 'asc')
  const page = ref(initial.page || 1)

  const rows = ref([])
  const total = ref(0)
  const lastPage = ref(1)
  const loading = ref(false)
  const error = ref('')

  let requestNumber = 0

  const pagination = computed(() => ({ total: total.value, current_page: page.value, last_page: lastPage.value }))

  async function load() {
    const current = ++requestNumber
    loading.value = true
    error.value = ''
    try {
      const params = new URLSearchParams(buildFilterQuery())
      params.set('sort', sort.value)
      params.set('dir', dir.value)
      params.set('per_page', String(perPage.value))
      if (page.value > 1) params.set('page', String(page.value))

      const data = await getJson('/api/tracks?' + params.toString())
      if (current !== requestNumber) return

      const tracks = data.tracks
      // После смены фильтра текущая страница может оказаться за пределами
      // результата — тогда возвращаемся на последнюю существующую.
      if (tracks.last_page > 0 && page.value > tracks.last_page) {
        page.value = tracks.last_page
        return load()
      }
      rows.value = tracks.data
      total.value = tracks.total
      lastPage.value = tracks.last_page
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
    rows, total, lastPage, loading, error, pagination,
    load, goToPage,
  }
}
