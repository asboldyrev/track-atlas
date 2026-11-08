<script setup>
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue'
import { Music2, Search, RotateCcw, Plus, Tags, ChevronLeft, ChevronRight, LoaderCircle, CheckCheck } from '@lucide/vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Checkbox } from '@/components/ui/checkbox'
import { Badge } from '@/components/ui/badge'
import { Card } from '@/components/ui/card'

const filters = reactive({ search: '', genres: [], moods: [], untagged: '', page: 1 })
const rows = ref([])
const genreTags = ref([])
const moodTags = ref([])
const tagTranslations = ref({ genre: {}, mood: {} })
const translationTagId = ref('')
const translationValue = ref('')
const pagination = reactive({ total: 0, current_page: 1, last_page: 1 })
const loading = ref(false)
const saving = ref(false)
const error = ref('')
const notice = ref('')
const editingId = ref(null)
const editingTags = ref([])
const selected = ref([])
const newTagName = ref('')
const newTagType = ref('genre')
const batchTagId = ref('')
const batchMode = ref('add')
const aiBusy = ref(false)
const aiUsage = ref(null)
const aiProgress = ref(null)
const aiCancelRequested = ref(false)
let requestNumber = 0
let searchTimer

const allTags = computed(() => [...genreTags.value, ...moodTags.value])
function translatedLabel(type, name, override = null) {
  const ru = override?.trim() || tagTranslations.value[type]?.[name.trim().toLocaleLowerCase()] || ''
  return ru && ru.toLocaleLowerCase() !== name.toLocaleLowerCase() ? ru + ' (' + name + ')' : name
}
function tagLabel(tag) {
  return translatedLabel(tag.type, tag.name, tag.name_ru)
}
function selectedTranslationTagChanged() {
  const tag = allTags.value.find(item => item.id === Number(translationTagId.value))
  translationValue.value = tag?.name_ru || ''
}
async function saveTranslation() {
  const tag = allTags.value.find(item => item.id === Number(translationTagId.value))
  if (!tag) return
  saving.value = true
  error.value = ''
  try {
    await mutate('/tags/' + tag.id + '/translation', 'PUT', { name_ru: translationValue.value.trim() || null })
    notice.value = 'Перевод сохранён'
    await loadCatalog()
  } catch (e) { error.value = e.message }
  finally { saving.value = false }
}

const selectedTracks = computed(() => rows.value.filter(row => selected.value.includes(row.id)))

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

function changeUntagged(event) {
  filters.untagged = event.target.value
  filters.page = 1
  commitFilters()
}

function searchChanged(value) {
  filters.search = value
  filters.page = 1
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => commitFilters(true), 350)
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

async function loadCatalog() {
  const current = ++requestNumber
  loading.value = true
  error.value = ''
  try {
    const response = await fetch('/catalog?' + makeQuery(), { headers: { Accept: 'application/json' } })
    if (!response.ok) throw new Error('Не удалось загрузить каталог (HTTP ' + response.status + ')')
    const data = await response.json()
    if (current !== requestNumber) return
    rows.value = data.tracks.data
    Object.assign(pagination, {
      total: data.tracks.total,
      current_page: data.tracks.current_page,
      last_page: data.tracks.last_page,
    })
    genreTags.value = data.tags.genre || []
    moodTags.value = data.tags.mood || []
    tagTranslations.value = data.tag_translations || { genre: {}, mood: {} }
    selected.value = []
  } catch (e) {
    if (current === requestNumber) error.value = e.message
  } finally {
    if (current === requestNumber) loading.value = false
  }
}

async function mutate(url, method, body) {
  const token = document.querySelector('meta[name="csrf-token"]')?.content
  const response = await fetch(url, {
    method,
    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token || '' },
    body: JSON.stringify(body),
  })
  if (!response.ok) {
    const details = await response.json().catch(() => ({}))
    throw new Error(details.message || 'Ошибка сохранения (HTTP ' + response.status + ')')
  }
  return response.json()
}

function edit(track) {
  editingId.value = track.id
  editingTags.value = track.tags.map(tag => tag.id)
}

function toggleEditTag(id, checked) {
  editingTags.value = checked ? [...new Set([...editingTags.value, id])] : editingTags.value.filter(v => v !== id)
}

async function saveTrack(track) {
  saving.value = true
  error.value = ''
  try {
    await mutate('/tracks/' + track.id + '/tags', 'PUT', { tags: editingTags.value })
    editingId.value = null
    notice.value = 'Категории сохранены'
    await loadCatalog()
  } catch (e) { error.value = e.message }
  finally { saving.value = false }
}

async function addTag() {
  if (!newTagName.value.trim()) return
  saving.value = true
  error.value = ''
  try {
    await mutate('/tags', 'POST', { name: newTagName.value.trim(), type: newTagType.value })
    newTagName.value = ''
    notice.value = 'Категория добавлена'
    await loadCatalog()
  } catch (e) { error.value = e.message }
  finally { saving.value = false }
}

function toggleSelected(id, checked) {
  selected.value = checked ? [...new Set([...selected.value, id])] : selected.value.filter(v => v !== id)
}

function toggleAll(checked) {
  selected.value = checked ? rows.value.map(row => row.id) : []
}

async function applyBatch() {
  if (!selected.value.length || !batchTagId.value) return
  saving.value = true
  error.value = ''
  try {
    await mutate('/tracks/batch-tags', 'POST', {
      tracks: selected.value,
      tags: [Number(batchTagId.value)],
      mode: batchMode.value,
    })
    notice.value = 'Изменено треков: ' + selected.value.length
    await loadCatalog()
  } catch (e) { error.value = e.message }
  finally { saving.value = false }
}

async function classifySelected(mode = 'new') {
  if (aiBusy.value || saving.value || !selected.value.length) return

  const ids = [...new Set(selected.value)]
  const batches = []
  for (let index = 0; index < ids.length; index += 10) {
    batches.push(ids.slice(index, index + 10))
  }

  const action = mode === 'empty' ? 'повторно проверить пустые предложения для' : (mode === 'existing' ? 'повторно классифицировать ожидающие/отклонённые предложения для' : 'классифицировать')
  const confirmed = window.confirm(
    'Отправить метаданные ' + ids.length + ' треков в OpenRouter и ' + action
    + ' их? Максимум ' + batches.length + ' платных запросов (по 10 треков). '
    + 'Уже обработанные треки, не подходящие для выбранного действия, будут пропущены.'
  )
  if (!confirmed) return

  aiBusy.value = true
  aiCancelRequested.value = false
  aiUsage.value = null
  aiProgress.value = { completed: 0, total: batches.length, processed: 0, skipped: 0 }
  error.value = ''
  notice.value = ''

  let failure = null
  let hasUsage = false
  let allCostsProvided = true
  let totalCost = 0
  let promptTokens = 0
  let completionTokens = 0
  let promptReported = true
  let completionReported = true

  try {
    for (const batch of batches) {
      if (aiCancelRequested.value) break

      try {
        const result = await mutate('/ai/classify', 'POST', {
          tracks: batch,
          retry_empty: mode === 'empty',
          retry_existing: mode === 'existing',
        })
        aiProgress.value.processed += result.processed || 0
        aiProgress.value.skipped += result.skipped || 0
        if (result.usage) {
          hasUsage = true
          const usage = result.usage
          if (usage.cost_usd === null || usage.cost_usd === undefined) {
            allCostsProvided = false
          } else {
            totalCost += Number(usage.cost_usd)
          }
          if (usage.prompt_tokens === null || usage.prompt_tokens === undefined) {
            promptReported = false
          } else {
            promptTokens += Number(usage.prompt_tokens)
          }
          if (usage.completion_tokens === null || usage.completion_tokens === undefined) {
            completionReported = false
          } else {
            completionTokens += Number(usage.completion_tokens)
          }
        }
        aiProgress.value.completed += 1
      } catch (e) {
        failure = e
        break
      }
    }
  } finally {
    const summary = { ...aiProgress.value }
    const cancelled = aiCancelRequested.value
    aiUsage.value = hasUsage ? {
      prompt_tokens: promptReported ? promptTokens : null,
      completion_tokens: completionReported ? completionTokens : null,
      cost_usd: allCostsProvided ? totalCost : null,
    } : null

    // Refresh only after the sequence: loadCatalog clears page selection.
    await loadCatalog()
    aiBusy.value = false
    aiCancelRequested.value = false
    aiProgress.value = null

    const outcome = 'Пакетов: ' + summary.completed + '/' + summary.total
      + '. Предложений: ' + summary.processed + '. Пропущено: ' + summary.skipped + '.'
    if (failure) {
      error.value = 'Остановка на ошибке: ' + failure.message + '. ' + outcome
        + ' Результаты успешных пакетов сохранены.'
    } else if (cancelled) {
      notice.value = 'Остановлено после текущего запроса. ' + outcome
    } else {
      notice.value = 'AI-классификация завершена. ' + outcome
    }
  }
}

function cancelClassification() {
  // In-flight requests cannot be cancelled safely; stop before the next batch.
  aiCancelRequested.value = true
}

async function decideSuggestion(suggestion, decision) {
  saving.value = true
  error.value = ''
  try {
    await mutate('/ai/suggestions/' + suggestion.id, 'PUT', { decision })
    notice.value = decision === 'accept' ? 'Предложенные теги применены' : 'Предложение отклонено'
    await loadCatalog()
  } catch (e) {
    error.value = e.message
  } finally {
    saving.value = false
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
  window.removeEventListener('popstate', backNavigation)
})
</script>

<template>
  <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
    <header class="mb-8 flex flex-wrap items-center justify-between gap-4">
      <div class="flex items-center gap-3">
        <div class="rounded-xl bg-primary p-3 text-primary-foreground"><Music2 :size="25" /></div>
        <div>
          <h1 class="text-2xl font-semibold tracking-tight">Track Atlas</h1>
          <p class="text-sm text-muted-foreground">Музыкальные референсы по жанрам и настроениям</p>
        </div>
      </div>
      <Badge variant="outline" class="gap-2"><span class="size-2 rounded-full bg-emerald-500"></span> Локальная библиотека</Badge>
    </header>

    <div v-if="error" role="alert" class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ error }}</div>
    <div v-if="notice" role="status" class="mb-4 flex items-center justify-between rounded-lg border border-border bg-white p-3 text-sm">
      {{ notice }} <Button variant="ghost" size="sm" @click="notice = ''">Закрыть</Button>
    </div>

    <div class="grid items-start gap-5 lg:grid-cols-[280px_minmax(0,1fr)]">
      <Card class="p-5">
        <div class="mb-5 flex items-center justify-between">
          <h2 class="font-semibold">Фильтры</h2>
          <Button variant="ghost" size="sm" @click="clearFilters"><RotateCcw :size="14" /> Сбросить</Button>
        </div>

        <label class="mb-4 block">
          <span class="mb-2 block text-sm font-medium">Поиск</span>
          <span class="relative block">
            <Search :size="16" class="pointer-events-none absolute left-3 top-2.5 text-muted-foreground" />
            <Input :model-value="filters.search" class="pl-9" placeholder="Трек или исполнитель" @update:model-value="searchChanged" />
          </span>
        </label>

        <fieldset class="mb-5">
          <legend class="mb-2 text-sm font-semibold">Жанры</legend>
          <p v-if="!genreTags.length" class="text-sm text-muted-foreground">Пока нет жанров</p>
          <div class="max-h-56 space-y-2 overflow-y-auto pr-1">
            <label v-for="tag in genreTags" :key="tag.id" class="flex cursor-pointer items-center gap-2 text-sm">
              <Checkbox :model-value="filters.genres.includes(tag.id)" @update:model-value="toggleFilter('genres', tag.id, $event)" />
              <span>{{ tagLabel(tag) }}</span>
            </label>
          </div>
        </fieldset>

        <fieldset class="mb-5">
          <legend class="mb-2 text-sm font-semibold">Настроения / Vibe</legend>
          <p v-if="!moodTags.length" class="text-sm text-muted-foreground">Пока нет настроений</p>
          <div class="max-h-56 space-y-2 overflow-y-auto pr-1">
            <label v-for="tag in moodTags" :key="tag.id" class="flex cursor-pointer items-center gap-2 text-sm">
              <Checkbox :model-value="filters.moods.includes(tag.id)" @update:model-value="toggleFilter('moods', tag.id, $event)" />
              <span>{{ tagLabel(tag) }}</span>
            </label>
          </div>
        </fieldset>

        <label class="mb-5 block text-sm font-medium">
          Без категорий
          <select :value="filters.untagged" class="mt-2 h-9 w-full rounded-md border border-input bg-background px-2 text-sm" @change="changeUntagged">
            <option value="">Все треки</option>
            <option value="genre">Без жанров</option>
            <option value="mood">Без настроений</option>
            <option value="all">Без любых категорий</option>
          </select>
        </label>

        <form class="border-t border-border pt-5" @submit.prevent="addTag">
          <h3 class="mb-3 text-sm font-semibold">Добавить категорию</h3>
          <select v-model="newTagType" class="mb-2 h-9 w-full rounded-md border border-input bg-background px-2 text-sm">
            <option value="genre">Жанр</option>
            <option value="mood">Настроение</option>
          </select>
          <Input v-model="newTagName" placeholder="Название" class="mb-2" />
          <Button size="sm" class="w-full" :disabled="saving || !newTagName.trim()" type="submit"><Plus :size="15" /> Добавить</Button>
        </form>
        <form class="mt-5 border-t border-border pt-5" @submit.prevent="saveTranslation">
          <h3 class="mb-2 text-sm font-semibold">Переводы категорий</h3>
          <p class="mb-3 text-xs text-muted-foreground">Русское название можно изменить. Оригинал и фильтры сохраняются.</p>
          <select v-model="translationTagId" class="mb-2 h-9 w-full rounded-md border border-input bg-background px-2 text-sm" @change="selectedTranslationTagChanged">
            <option value="">Выбрать категорию</option>
            <option v-for="tag in allTags" :key="tag.id" :value="tag.id">{{ tagLabel(tag) }}</option>
          </select>
          <Input v-model="translationValue" :disabled="!translationTagId" placeholder="Перевод на русский" class="mb-2" />
          <Button type="submit" size="sm" variant="outline" class="w-full" :disabled="saving || !translationTagId">Сохранить перевод</Button>
        </form>
      </Card>

      <Card class="min-w-0 p-5">
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
          <div>
            <h2 class="text-lg font-semibold">Композиции <span class="text-muted-foreground">({{ pagination.total }})</span></h2>
            <p class="text-xs text-muted-foreground">ИЛИ внутри группы · И между жанрами и настроениями</p>
          </div>
          <LoaderCircle v-if="loading" class="animate-spin text-muted-foreground" :size="20" aria-label="Загрузка" />
        </div>

        <div v-if="rows.length" class="mb-4 flex items-center gap-3 border-b border-border pb-4">
          <label class="flex cursor-pointer items-center gap-2 text-xs text-muted-foreground">
            <Checkbox :model-value="rows.length > 0 && selected.length === rows.length" @update:model-value="toggleAll" />
            Выделить на странице
          </label>
          <span v-if="selected.length" class="text-xs font-medium">Выбрано: {{ selected.length }}</span>
        </div>

        <div v-if="selected.length" class="mb-4 flex flex-wrap items-center gap-2 rounded-lg bg-muted p-3">
          <Tags :size="18" class="text-muted-foreground" />
          <select v-model="batchTagId" aria-label="Категория для массового действия" class="h-9 min-w-40 rounded-md border border-input bg-background px-2 text-sm">
            <option value="">Выбрать категорию</option>
            <option v-for="tag in allTags" :key="tag.id" :value="tag.id">{{ tagLabel(tag) }}</option>
          </select>
          <select v-model="batchMode" aria-label="Массовое действие" class="h-9 rounded-md border border-input bg-background px-2 text-sm">
            <option value="add">Добавить</option>
            <option value="remove">Удалить</option>
          </select>
          <Button size="sm" :disabled="saving || !batchTagId" @click="applyBatch"><CheckCheck :size="16" /> Применить</Button>
          <Button variant="outline" size="sm" :disabled="saving || aiBusy" @click="classifySelected('new')">
            <LoaderCircle v-if="aiBusy" :size="16" class="animate-spin" />
            AI: классифицировать выбранные
          </Button>
          <Button variant="outline" size="sm" :disabled="saving || aiBusy" @click="classifySelected('empty')">
            Повторить пустые AI-предложения
          </Button>
          <Button variant="outline" size="sm" :disabled="saving || aiBusy" @click="classifySelected('existing')">
            Повторить выбранные AI-предложения
          </Button>
        </div>

        <div v-if="aiProgress" class="mb-4 rounded-lg border border-border bg-muted p-3 text-sm" role="status">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <span>AI: обработано пакетов {{ aiProgress.completed }} / {{ aiProgress.total }} · треков {{ aiProgress.processed }} · пропущено {{ aiProgress.skipped }}</span>
            <Button variant="outline" size="sm" :disabled="aiCancelRequested" @click="cancelClassification">
              {{ aiCancelRequested ? 'Останавливается…' : 'Остановить после текущего запроса' }}
            </Button>
          </div>
          <div class="mt-2 h-2 overflow-hidden rounded-full bg-border">
            <div class="h-full bg-primary transition-all" :style="{ width: Math.round(aiProgress.completed / aiProgress.total * 100) + '%' }"></div>
          </div>
        </div>

        <p v-if="aiUsage" class="mb-4 text-xs text-muted-foreground">
          OpenRouter: вход {{ aiUsage.prompt_tokens ?? 'н/д' }} токенов,
          выход {{ aiUsage.completion_tokens ?? 'н/д' }} токенов,
          стоимость {{ aiUsage.cost_usd === null ? 'не предоставлена' : '$' + aiUsage.cost_usd.toFixed(6) }}
        </p>

        <div v-if="!rows.length && !loading" class="rounded-xl border border-dashed border-border py-12 text-center text-sm text-muted-foreground">
          Треки не найдены. Измени фильтры или выполни php artisan music:scan.
        </div>

        <div v-for="track in rows" :key="track.id" class="border-b border-border py-4 last:border-0">
          <div class="flex items-start gap-3">
            <Checkbox :model-value="selected.includes(track.id)" class="mt-1" @update:model-value="toggleSelected(track.id, $event)" />
            <div class="min-w-0 flex-1">
              <h3 class="break-words text-sm font-semibold">{{ track.artist ? track.artist + ' — ' : '' }}{{ track.title }}</h3>
              <p v-if="track.album" class="mt-1 text-xs text-muted-foreground">{{ track.album }}</p>
              <div class="mt-2 flex flex-wrap gap-1.5">
                <Badge v-for="tag in track.tags" :key="tag.id" :variant="tag.type === 'mood' ? 'outline' : 'secondary'">{{ tagLabel(tag) }}</Badge>
                <span v-if="!track.tags.length" class="text-xs text-muted-foreground">Без категорий</span>
              </div>
              <div v-for="suggestion in (track.metadata_suggestions || []).filter(s => s.status === 'pending')" :key="suggestion.id" class="mt-3 rounded-lg border border-border bg-muted/60 p-3 text-xs">
                <div class="mb-2 font-semibold">Предложение OpenRouter <span class="font-normal text-muted-foreground">({{ suggestion.model }})</span></div>
                <div class="mb-2 flex flex-wrap gap-1">
                  <Badge v-for="genre in suggestion.genres || []" :key="'g' + genre" variant="secondary">{{ translatedLabel('genre', genre) }}</Badge>
                  <Badge v-for="mood in suggestion.moods || []" :key="'m' + mood" variant="outline">{{ translatedLabel('mood', mood) }}</Badge>
                  <span v-if="!(suggestion.genres || []).length && !(suggestion.moods || []).length" class="text-muted-foreground">Модель не смогла определить категории</span>
                </div>
                <div class="flex gap-2">
                  <Button size="sm" :disabled="saving || aiBusy" @click="decideSuggestion(suggestion, 'accept')">Принять</Button>
                  <Button size="sm" variant="outline" :disabled="saving || aiBusy" @click="decideSuggestion(suggestion, 'reject')">Отклонить</Button>
                </div>
              </div>
            </div>
            <Button variant="outline" size="sm" @click="editingId === track.id ? editingId = null : edit(track)">
              {{ editingId === track.id ? 'Закрыть' : 'Теги' }}
            </Button>
          </div>

          <div v-if="editingId === track.id" class="ml-7 mt-4 rounded-lg border border-border bg-muted/50 p-4">
            <div v-for="(group, title) in { 'Жанры': genreTags, 'Настроения': moodTags }" :key="title" class="mb-4">
              <h4 class="mb-2 text-xs font-semibold">{{ title }}</h4>
              <div class="flex flex-wrap gap-x-4 gap-y-2">
                <label v-for="tag in group" :key="tag.id" class="flex cursor-pointer items-center gap-2 text-sm">
                  <Checkbox :model-value="editingTags.includes(tag.id)" @update:model-value="toggleEditTag(tag.id, $event)" />
                  {{ tagLabel(tag) }}
                </label>
              </div>
            </div>
            <Button size="sm" :disabled="saving" @click="saveTrack(track)">Сохранить категории</Button>
          </div>
        </div>

        <div v-if="pagination.last_page > 1" class="mt-6 flex items-center justify-between">
          <span class="text-xs text-muted-foreground">Страница {{ pagination.current_page }} из {{ pagination.last_page }}</span>
          <div class="flex gap-2">
            <Button variant="outline" size="sm" :disabled="loading || pagination.current_page <= 1" @click="changePage(pagination.current_page - 1)"><ChevronLeft :size="16" /> Назад</Button>
            <Button variant="outline" size="sm" :disabled="loading || pagination.current_page >= pagination.last_page" @click="changePage(pagination.current_page + 1)">Далее <ChevronRight :size="16" /></Button>
          </div>
        </div>
      </Card>
    </div>
  </div>
</template>
