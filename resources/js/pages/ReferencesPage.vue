<script setup>
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue'
import { Card } from '@/components/ui/card'
import TagFilterBlock from '@/components/TagFilterBlock.vue'
import TrackLine from '@/components/TrackLine.vue'
import { useTags } from '@/composables/useTags'
import { useTracks } from '@/composables/useTracks'

// Страница референсов: одна колонка из трёх блоков (Жанры → Настроения →
// Треки). Фильтры и режимы живут в URL, справочник загружается один раз.
const {
  genreTags, moodTags,
  genreLoading, moodLoading, genreError, moodError,
  loadTags, reloadGenres, reloadMoods,
} = useTags()

const filters = reactive({ genres: [], moods: [] })
const genresMode = ref('or')
const moodsMode = ref('or')

// Значения режима, не равные «or», трактуются как «и»; неизвестные id из
// справочника убираются после его загрузки (см. syncIdsWithDictionary).
function readUrl() {
  const params = new URLSearchParams(window.location.search)
  const ids = name => [...new Set(params.getAll(name).map(Number).filter(Number.isInteger))]
  return {
    genres: ids('genres[]'),
    moods: ids('moods[]'),
    genresMode: params.get('genres_mode') === 'and' ? 'and' : 'or',
    moodsMode: params.get('moods_mode') === 'and' ? 'and' : 'or',
    page: Math.max(1, Number.parseInt(params.get('page') || '1', 10) || 1),
  }
}

function applyUrl(url) {
  filters.genres = url.genres
  filters.moods = url.moods
  genresMode.value = url.genresMode
  moodsMode.value = url.moodsMode
}

const initialUrl = readUrl()
applyUrl(initialUrl)

const pager = useTracks(makeQuery, { page: initialUrl.page })
const { rows, loading, error, total, lastPage, page, load, goToPage } = pager

// Первая загрузка списка ещё не началась (идёт справочник) — показываем
// заглушки строк, а не «пустой каталог».
const started = ref(false)

const hasActiveFilters = computed(() => Boolean(filters.genres.length || filters.moods.length))

function makeQuery() {
  const params = new URLSearchParams()
  for (const id of filters.genres) params.append('genres[]', String(id))
  for (const id of filters.moods) params.append('moods[]', String(id))
  if (genresMode.value === 'and') params.set('genres_mode', 'and')
  if (moodsMode.value === 'and') params.set('moods_mode', 'and')
  return params.toString()
}

function makeUrlQuery() {
  const params = new URLSearchParams(makeQuery())
  if (page.value > 1) params.set('page', String(page.value))
  return params.toString()
}

function urlFor(query) {
  return '/' + (query ? '?' + query : '')
}

function commitFilters(replace = false) {
  const query = makeUrlQuery()
  window.history[replace ? 'replaceState' : 'pushState']({}, '', urlFor(query))
  void load()
}

// Смена фильтра, режима или страницы пишет страницу 1: результат всегда
// показывается с начала.
function commitFromFirstPage() {
  page.value = 1
  commitFilters()
}

function toggleValue(group, id, checked) {
  filters[group] = checked
    ? [...new Set([...filters[group], id])]
    : filters[group].filter(value => value !== id)
  commitFromFirstPage()
}

function changeMode(group, value) {
  if (group === 'genres') genresMode.value = value
  else moodsMode.value = value
  commitFromFirstPage()
}

function clearFilters() {
  filters.genres = []
  filters.moods = []
  genresMode.value = 'or'
  moodsMode.value = 'or'
  commitFromFirstPage()
}

function changePage(next) {
  if (!goToPage(next)) return
  commitFilters()
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

// Неизвестный id (нет в справочнике своего типа) тихо убирается из состояния
// и адреса. Группу с упавшим справочником не трогаем: значения могут быть
// валидными, просто словарь не загрузился.
function syncIdsWithDictionary() {
  let changed = false
  if (!genreError.value && filters.genres.some(id => !genreTags.value.some(tag => tag.id === id))) {
    filters.genres = filters.genres.filter(id => genreTags.value.some(tag => tag.id === id))
    changed = true
  }
  if (!moodError.value && filters.moods.some(id => !moodTags.value.some(tag => tag.id === id))) {
    filters.moods = filters.moods.filter(id => moodTags.value.some(tag => tag.id === id))
    changed = true
  }
  return changed
}

async function retryGenres() {
  await reloadGenres()
  void load()
}

async function retryMoods() {
  await reloadMoods()
  void load()
}

function backNavigation() {
  const url = readUrl()
  applyUrl(url)
  page.value = url.page
  void load()
}

onMounted(async () => {
  window.addEventListener('popstate', backNavigation)
  await loadTags()
  // Справочник пришёл — подчищаем неизвестные id и правим адрес без записи в
  // историю, затем грузим треки (часть id могла пропасть).
  if (syncIdsWithDictionary()) {
    window.history.replaceState({}, '', urlFor(makeUrlQuery()))
  }
  started.value = true
  await load()
})

onUnmounted(() => {
  window.removeEventListener('popstate', backNavigation)
})

// Объявление результата для скринридера; меняется только вместе с числом.
const announcement = computed(() => (loading.value || !started.value ? '' : foundText(total.value)))

function foundText(count) {
  const mod10 = count % 10
  const mod100 = count % 100
  if (mod10 === 1 && mod100 !== 11) return 'Найден ' + count + ' трек'
  if (mod10 >= 2 && mod10 <= 4 && (mod100 < 10 || mod100 >= 20)) return 'Найдено ' + count + ' трека'
  return 'Найдено ' + count + ' треков'
}
</script>

<template>
  <div class="flex flex-col gap-4 sm:gap-5">
    <TagFilterBlock
      heading-id="genres-title"
      title="Жанры"
      :tag-list="genreTags"
      :selected="filters.genres"
      :mode="genresMode"
      mode-label="Режим выбора жанров"
      :loading="genreLoading"
      :error="Boolean(genreError)"
      loading-text="Загрузка жанров…"
      error-text="Не удалось загрузить жанры"
      empty-text="Пока нет жанров"
      @toggle="(id, checked) => toggleValue('genres', id, checked)"
      @update:mode="value => changeMode('genres', value)"
      @retry="retryGenres"
    />

    <TagFilterBlock
      heading-id="moods-title"
      title="Настроения"
      :tag-list="moodTags"
      :selected="filters.moods"
      :mode="moodsMode"
      mode-label="Режим выбора настроений"
      :loading="moodLoading"
      :error="Boolean(moodError)"
      loading-text="Загрузка настроений…"
      error-text="Не удалось загрузить настроения"
      empty-text="Пока нет настроений"
      @toggle="(id, checked) => toggleValue('moods', id, checked)"
      @update:mode="value => changeMode('moods', value)"
      @retry="retryMoods"
    />

    <Card class="p-4 sm:p-5">
      <h2 id="tracks-title" class="text-sm font-semibold leading-5">Треки</h2>

      <p role="status" aria-live="polite" class="sr-only">{{ announcement }}</p>

      <div
        v-if="error"
        role="alert"
        class="mt-3 flex items-center gap-3 rounded-lg border border-destructive/30 bg-destructive/10 px-3.5 py-3 text-[13px] leading-[18px] text-destructive"
      >
        <span>Не удалось загрузить треки</span>
        <button type="button" class="font-semibold underline underline-offset-4" @click="load">Повторить</button>
      </div>

      <div
        v-else-if="(loading || !started) && !rows.length"
        role="status"
        aria-busy="true"
        class="mt-3"
      >
        <span class="sr-only">Загрузка треков…</span>
        <div
          v-for="n in 3"
          :key="n"
          aria-hidden="true"
          class="border-b border-border py-3.5 first:pt-0.5 last:border-0 last:pb-0.5"
        >
          <span class="block h-3.5 w-[46%] animate-pulse rounded bg-foreground/10 motion-reduce:animate-none"></span>
          <span class="mt-2 block h-3.5 w-[28%] animate-pulse rounded bg-foreground/10 motion-reduce:animate-none"></span>
        </div>
      </div>

      <div
        v-else-if="!rows.length && hasActiveFilters"
        class="mt-3 rounded-xl border border-dashed border-border px-4 py-8 text-center text-sm leading-5 text-muted-foreground"
      >
        <p>Ничего не подходит под выбранные фильтры</p>
        <button
          type="button"
          class="mt-2.5 font-medium text-foreground underline underline-offset-4"
          @click="clearFilters"
        >Сбросить фильтры</button>
      </div>

      <div
        v-else-if="!rows.length"
        class="mt-3 rounded-xl border border-dashed border-border px-4 py-8 text-center text-sm leading-5 text-muted-foreground"
      >
        <p>В каталоге пока нет треков</p>
      </div>

      <template v-else>
        <ul
          class="mt-3 divide-y divide-border"
          :class="loading ? 'opacity-50' : ''"
          :aria-busy="loading ? 'true' : undefined"
        >
          <TrackLine v-for="track in rows" :key="track.id" :track="track" />
        </ul>

        <div v-if="lastPage > 1" class="mt-3.5 flex items-center justify-between gap-3">
          <span class="text-[13px] leading-4 text-muted-foreground tabular-nums">страница {{ page }} из {{ lastPage }}</span>
          <div class="flex gap-2">
            <button
              type="button"
              class="inline-flex h-7 items-center rounded-lg border border-border px-2.5 text-xs font-medium leading-4 hover:bg-accent disabled:opacity-40 disabled:hover:bg-transparent"
              :disabled="loading || page <= 1"
              @click="changePage(page - 1)"
            >Назад</button>
            <button
              type="button"
              class="inline-flex h-7 items-center rounded-lg border border-border px-2.5 text-xs font-medium leading-4 hover:bg-accent disabled:opacity-40 disabled:hover:bg-transparent"
              :disabled="loading || page >= lastPage"
              @click="changePage(page + 1)"
            >Вперёд</button>
          </div>
        </div>
      </template>
    </Card>
  </div>
</template>
