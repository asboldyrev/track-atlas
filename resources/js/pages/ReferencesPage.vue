<script setup>
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue'
import { ChevronLeft, ChevronRight, Link2, Copy, RotateCcw, X } from '@lucide/vue'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import FilterPanel from '@/components/FilterPanel.vue'
import TrackList from '@/components/TrackList.vue'
import { useCatalog } from '@/composables/useCatalog'

const { genreTags, moodTags, allTags, tagLabel, fetchCatalog } = useCatalog()

const untaggedLabels = { genre: 'Без жанров', mood: 'Без настроений', all: 'Без любых категорий' }

const filters = reactive({ search: '', genres: [], moods: [], untagged: '', page: 1 })
const rows = ref([])
const pagination = reactive({ total: 0, current_page: 1, last_page: 1 })
const loading = ref(false)
const error = ref('')
const linkCopied = ref(false)
let requestNumber = 0
let searchTimer
let copyTimer

const hasActiveFilters = computed(() =>
  Boolean(filters.search.trim() || filters.genres.length || filters.moods.length || filters.untagged),
)

const activeChips = computed(() => {
  const chips = []
  const labelFor = id => {
    const tag = allTags.value.find(t => t.id === id)
    return tag ? tagLabel(tag) : 'Категория №' + id
  }
  if (filters.search.trim()) chips.push({ key: 'search', kind: 'search', label: 'Поиск: «' + filters.search.trim() + '»' })
  for (const id of filters.genres) chips.push({ key: 'genres-' + id, kind: 'genres', id, label: labelFor(id) })
  for (const id of filters.moods) chips.push({ key: 'moods-' + id, kind: 'moods', id, label: labelFor(id) })
  if (filters.untagged) chips.push({ key: 'untagged', kind: 'untagged', label: untaggedLabels[filters.untagged] })
  return chips
})

function readUrl() {
  const params = new URLSearchParams(window.location.search)
  filters.search = params.get('search') || ''
  filters.genres = params.getAll('genres[]').map(Number).filter(Number.isInteger)
  filters.moods = params.getAll('moods[]').map(Number).filter(Number.isInteger)
  filters.untagged = ['genre', 'mood', 'all'].includes(params.get('untagged')) ? params.get('untagged') : ''
  filters.page = Math.max(1, Number.parseInt(params.get('page') || '1', 10) || 1)
}

function makeQuery() {
  const params = new URLSearchParams()
  if (filters.search.trim()) params.set('search', filters.search.trim())
  for (const id of filters.genres) params.append('genres[]', String(id))
  for (const id of filters.moods) params.append('moods[]', String(id))
  if (filters.untagged) params.set('untagged', filters.untagged)
  if (filters.page > 1) params.set('page', String(filters.page))
  return params.toString()
}

function commitFilters(replace = false) {
  const query = makeQuery()
  window.history[replace ? 'replaceState' : 'pushState']({}, '', '/' + (query ? '?' + query : ''))
  void loadCatalog()
}

function toggleFilter(group, id, checked) {
  filters[group] = checked ? [...new Set([...filters[group], id])] : filters[group].filter(v => v !== id)
  filters.page = 1
  commitFilters()
}

function changeUntagged(value) {
  filters.untagged = value
  filters.page = 1
  commitFilters()
}

function searchChanged(value) {
  filters.search = value
  filters.page = 1
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => commitFilters(true), 350)
}

function removeChip(chip) {
  if (chip.kind === 'search') filters.search = ''
  else if (chip.kind === 'untagged') filters.untagged = ''
  else filters[chip.kind] = filters[chip.kind].filter(id => id !== chip.id)
  filters.page = 1
  commitFilters()
}

function clearFilters() {
  clearTimeout(searchTimer)
  Object.assign(filters, { search: '', genres: [], moods: [], untagged: '', page: 1 })
  commitFilters()
}

function changePage(next) {
  if (next < 1 || next > pagination.last_page || loading.value) return
  filters.page = next
  commitFilters()
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

async function copyLink() {
  const url = window.location.href
  try {
    await navigator.clipboard.writeText(url)
  } catch {
    window.prompt('Скопируйте ссылку:', url)
    return
  }
  linkCopied.value = true
  clearTimeout(copyTimer)
  copyTimer = setTimeout(() => { linkCopied.value = false }, 2000)
}

async function loadCatalog() {
  const current = ++requestNumber
  loading.value = true
  error.value = ''
  try {
    const data = await fetchCatalog(makeQuery())
    if (current !== requestNumber) return
    rows.value = data.tracks.data
    Object.assign(pagination, {
      total: data.tracks.total,
      current_page: data.tracks.current_page,
      last_page: data.tracks.last_page,
    })
  } catch (e) {
    if (current === requestNumber) error.value = e.message
  } finally {
    if (current === requestNumber) loading.value = false
  }
}

function backNavigation() {
  clearTimeout(searchTimer)
  readUrl()
  void loadCatalog()
}

onMounted(() => {
  readUrl()
  void loadCatalog()
  window.addEventListener('popstate', backNavigation)
})

onUnmounted(() => {
  clearTimeout(searchTimer)
  clearTimeout(copyTimer)
  window.removeEventListener('popstate', backNavigation)
})
</script>

<template>
  <div class="grid items-start gap-5 lg:grid-cols-[280px_minmax(0,1fr)]">
    <div class="lg:sticky lg:top-6 lg:max-h-[calc(100vh-3rem)] lg:overflow-y-auto">
      <FilterPanel
        :filters="filters"
        :genre-tags="genreTags"
        :mood-tags="moodTags"
        @search="searchChanged"
        @toggle="toggleFilter"
        @untagged="changeUntagged"
        @reset="clearFilters"
      />
    </div>

    <Card class="min-w-0 p-5">
      <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 class="text-lg font-semibold">Референсы <span class="text-muted-foreground">({{ pagination.total }})</span></h2>
          <p class="text-xs text-muted-foreground">Подбор треков по жанрам и настроениям. ИЛИ внутри группы · И между жанрами и настроениями.</p>
        </div>
        <Button variant="outline" size="sm" @click="copyLink">
          <Copy v-if="!linkCopied" :size="15" />
          <Link2 v-else :size="15" />
          {{ linkCopied ? 'Ссылка скопирована' : 'Скопировать ссылку' }}
        </Button>
      </div>

      <div v-if="activeChips.length" class="mb-4 flex flex-wrap items-center gap-2 border-b border-border pb-4">
        <span class="text-xs text-muted-foreground">Активные фильтры:</span>
        <button
          v-for="chip in activeChips"
          :key="chip.key"
          type="button"
          class="inline-flex items-center gap-1 rounded-full border border-border bg-secondary px-2.5 py-1 text-xs hover:bg-accent"
          @click="removeChip(chip)"
        >
          {{ chip.label }} <X :size="12" aria-hidden="true" />
          <span class="sr-only">Снять фильтр</span>
        </button>
        <Button variant="ghost" size="sm" class="ml-auto" @click="clearFilters"><RotateCcw :size="14" /> Сбросить всё</Button>
      </div>

      <div v-if="error" role="alert" class="mb-4 rounded-lg border border-destructive/30 bg-destructive/10 p-3 text-sm text-destructive">
        {{ error }}
        <Button variant="ghost" size="sm" class="ml-2" @click="loadCatalog">Повторить</Button>
      </div>

      <TrackList :rows="rows" :loading="loading" @changed="loadCatalog">
        <template #empty>
          <template v-if="hasActiveFilters">
            <p class="text-sm text-muted-foreground">Ничего не найдено по этим фильтрам.</p>
            <Button variant="outline" size="sm" class="mt-3" @click="clearFilters"><RotateCcw :size="14" /> Сбросить фильтры</Button>
          </template>
          <p v-else class="text-sm text-muted-foreground">
            В библиотеке пока нет треков. Добавьте музыкальные файлы в папку библиотеки и запустите сканирование — после этого треки появятся здесь.
          </p>
        </template>
      </TrackList>

      <div v-if="pagination.last_page > 1" class="mt-6 flex items-center justify-between">
        <span class="text-xs text-muted-foreground">Страница {{ pagination.current_page }} из {{ pagination.last_page }}</span>
        <div class="flex gap-2">
          <Button variant="outline" size="sm" :disabled="loading || pagination.current_page <= 1" @click="changePage(pagination.current_page - 1)"><ChevronLeft :size="16" /> Назад</Button>
          <Button variant="outline" size="sm" :disabled="loading || pagination.current_page >= pagination.last_page" @click="changePage(pagination.current_page + 1)">Далее <ChevronRight :size="16" /></Button>
        </div>
      </div>
    </Card>
  </div>
</template>
