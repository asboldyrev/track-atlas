<script setup>
import { computed, nextTick, onMounted, reactive, ref } from 'vue'
import { ChevronLeft, ChevronRight, CornerDownLeft, Keyboard, RotateCcw, Sparkles, AlertTriangle, X } from '@lucide/vue'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { Select } from '@/components/ui/select'
import BatchBar from '@/components/BatchBar.vue'
import ListToolbar from '@/components/ListToolbar.vue'
import TrackList from '@/components/TrackList.vue'
import { openRouterModel } from '@/composables/useApi'
import { useTags } from '@/composables/useTags'
import { useAsyncAction } from '@/composables/useAsyncAction'
import { useDensity } from '@/composables/useDensity'
import { useKeyboardShortcuts } from '@/composables/useKeyboardShortcuts'
import { useTracks, batchTags, TRUNCATED_LIMIT } from '@/composables/useTracks'
import { useSuggestions } from '@/composables/useSuggestions'

const { allTags, tagLabel, loadTags } = useTags()
const { classify } = useSuggestions()
const { compact, toggleCompact } = useDensity()

const untaggedLabels = { genre: 'без жанров', mood: 'без настроений', all: 'без любых категорий' }

const queue = reactive({ untagged: 'all' })

function makeQuery() {
  const params = new URLSearchParams()
  if (queue.untagged) params.set('untagged', queue.untagged)
  return params.toString()
}

const pager = useTracks(makeQuery, { page: 1, perPage: 30, sort: 'artist', dir: 'asc' })
const { rows, loading, error, total, lastPage, page, perPage, sort, dir, truncated, load, goToPage } = pager

const selected = ref([])

const batchTagId = ref('')
const batchMode = ref('add')
const batch = useAsyncAction()

// A10: 1–9 выбирают категорию из первых девяти — они же показаны в BatchBar
// как кликабельные чипы, чтобы соответствие цифра → категория было видно.
const quickTags = computed(() => allTags.value.slice(0, 9))

// --- Keyboard navigation over the queue ---
const activeIndex = ref(-1)
const helpOpen = ref(false)
const shortcutHint = ref('')
const helpCloseButton = ref(null)
const helpTrigger = ref(null)
const helpDialog = ref(null)
let helpReturnFocus = null
let hintTimer

const activeId = computed(() => rows.value[activeIndex.value]?.id ?? null)

function say(message) {
  shortcutHint.value = message
  clearTimeout(hintTimer)
  hintTimer = setTimeout(() => { shortcutHint.value = '' }, 4000)
}

function onRowActivate(id) {
  const index = rows.value.findIndex(row => row.id === id)
  if (index >= 0) activeIndex.value = index
}

function focusRow(index) {
  const row = rows.value[index]
  if (!row) return false
  activeIndex.value = index
  const element = document.getElementById('track-row-' + row.id)
  if (!element) return false
  element.focus()
  element.scrollIntoView({ block: 'nearest' })
  return true
}

function moveActive(delta) {
  if (helpOpen.value || aiPreflight.value || aiBusy.value) return false
  if (!rows.value.length) return false
  const base = activeIndex.value < 0 ? -1 : activeIndex.value
  const next = Math.min(rows.value.length - 1, Math.max(0, base + delta))
  if (next === activeIndex.value) return false
  return focusRow(next)
}

function toggleActiveSelection() {
  if (helpOpen.value || aiPreflight.value || aiBusy.value) return false
  const row = rows.value[activeIndex.value]
  if (!row) {
    say('Сначала выберите строку: j / k')
    return true
  }
  toggleSelected(row.id, !selected.value.includes(row.id))
  return true
}

function chooseQuickTag(position) {
  if (helpOpen.value || aiPreflight.value || aiBusy.value) return false
  const tag = quickTags.value[position - 1]
  if (!tag) {
    say('Категории ' + position + ' нет в списке 1–9')
    return true
  }
  batchTagId.value = String(tag.id)
  say('Категория для действия: ' + tagLabel(tag))
  return true
}

function isControlTarget(target) {
  const tag = target?.tagName?.toUpperCase()
  return tag === 'BUTTON' || tag === 'A' || tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT'
}

function applyShortcut() {
  if (helpOpen.value || aiPreflight.value || aiBusy.value) return false
  const ids = selected.value.length ? selected.value : (activeId.value ? [activeId.value] : [])
  if (!ids.length) {
    say('Отметьте строки пробелом или выберите j / k')
    return true
  }
  if (!batchTagId.value) {
    say('Сначала выберите категорию: 1–9')
    return true
  }
  void applyTo(ids)
  return true
}

// Ссылка на компонент Button — это его инстанс, а не DOM-узел: фокусируем
// корневой элемент через $el.
function focusNode(refValue) {
  const el = refValue?.$el || refValue
  el?.focus?.()
}

function openHelp() {
  helpReturnFocus = document.activeElement
  helpOpen.value = true
  nextTick(() => focusNode(helpCloseButton.value))
}

function closeHelp() {
  helpOpen.value = false
  nextTick(() => focusNode(helpReturnFocus || helpTrigger.value))
}

function toggleHelp() {
  if (helpOpen.value) closeHelp()
  else openHelp()
}

// Диалог справки модален: удерживаем фокус внутри него, пока он открыт.
function trapDialogFocus(event) {
  if (event.key !== 'Tab') return
  const root = helpDialog.value?.$el || helpDialog.value
  if (!root) return
  const focusables = [...root.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])')]
    .filter(el => !el.hasAttribute('disabled'))
  if (!focusables.length) return
  const first = focusables[0]
  const last = focusables[focusables.length - 1]
  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault()
    last.focus()
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault()
    first.focus()
  }
}

useKeyboardShortcuts({
  j: () => moveActive(1),
  k: () => moveActive(-1),
  ' ': event => (isControlTarget(event.target) ? false : toggleActiveSelection()),
  Enter: event => (isControlTarget(event.target) ? false : applyShortcut()),
  '?': () => { openHelp(); return true },
  Escape: () => {
    if (helpOpen.value) { closeHelp(); return true }
    return false
  },
  '1': () => chooseQuickTag(1),
  '2': () => chooseQuickTag(2),
  '3': () => chooseQuickTag(3),
  '4': () => chooseQuickTag(4),
  '5': () => chooseQuickTag(5),
  '6': () => chooseQuickTag(6),
  '7': () => chooseQuickTag(7),
  '8': () => chooseQuickTag(8),
  '9': () => chooseQuickTag(9),
})

// --- AI classification (local state, no global banner) ---
const model = openRouterModel()
const aiPreflight = ref(false)
const aiRetryEmpty = ref(false)
const aiBusy = ref(false)
const aiProgress = ref(null)
const aiUsage = ref(null)
const aiCancelRequested = ref(false)
const aiError = ref('')
const aiNotice = ref('')

const selectedIds = computed(() => [...new Set(selected.value)])
const requestCount = computed(() => Math.ceil(selectedIds.value.length / 10))
// Cost cannot be known before the run; the number of paid requests and the
// model are the drivers a user can weigh up front.
const estimatedCost = computed(() => requestCount.value * 0.0005)

function plural(n, one, few, many) {
  const m10 = n % 10
  const m100 = n % 100
  if (m10 === 1 && m100 !== 11) return one
  if (m10 >= 2 && m10 <= 4 && !(m100 >= 12 && m100 <= 14)) return few
  return many
}
const trackWord = computed(() => plural(selectedIds.value.length, 'трек', 'трека', 'треков'))
const requestWord = computed(() => plural(requestCount.value, 'запрос', 'запроса', 'запросов'))

async function loadQueue() {
  activeIndex.value = -1
  await load()
  selected.value = []
}

function changeQueueMode(value) {
  queue.untagged = value
  page.value = 1
  void loadQueue()
}

function changePage(next) {
  if (!goToPage(next)) return
  void loadQueue()
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

function setPerPage(value) {
  if (value === perPage.value) return
  perPage.value = value
  page.value = 1
  void loadQueue()
}

function setSort(value) {
  if (value === sort.value) return
  sort.value = value
  page.value = 1
  void loadQueue()
}

function setDir(value) {
  if (value === dir.value) return
  dir.value = value
  page.value = 1
  void loadQueue()
}

function toggleSelected(id, checked) {
  selected.value = checked ? [...new Set([...selected.value, id])] : selected.value.filter(v => v !== id)
}

function toggleAll(checked) {
  selected.value = checked ? rows.value.map(row => row.id) : []
}

function clearSelection() {
  selected.value = []
}

async function applyTo(ids) {
  if (!ids.length || !batchTagId.value) return
  const result = await batch.run(
    () => batchTags(ids, [Number(batchTagId.value)], batchMode.value),
    'Изменено треков: ' + ids.length,
  )
  if (result.ok) await loadQueue()
}

function applyBatch() {
  return applyTo(selected.value)
}

function openClassifyPrompt(retryEmpty = false) {
  if (!selectedIds.value.length || aiBusy.value) return
  aiRetryEmpty.value = retryEmpty
  aiError.value = ''
  aiNotice.value = ''
  aiPreflight.value = true
}

async function runClassification() {
  aiPreflight.value = false
  const ids = selectedIds.value
  const batches = []
  for (let index = 0; index < ids.length; index += 10) batches.push(ids.slice(index, index + 10))

  aiBusy.value = true
  aiCancelRequested.value = false
  aiError.value = ''
  aiNotice.value = ''
  aiUsage.value = null
  aiProgress.value = { completed: 0, total: batches.length, processed: 0, skipped: 0 }

  let failure = null
  let hasUsage = false
  let allCostsProvided = true
  let totalCost = 0
  let promptTokens = 0
  let completionTokens = 0
  let promptReported = true
  let completionReported = true

  try {
    for (const batchIds of batches) {
      if (aiCancelRequested.value) break
      try {
        const result = await classify(batchIds, { retryEmpty: aiRetryEmpty.value === true })
        aiProgress.value.processed += result.processed || 0
        aiProgress.value.skipped += result.skipped || 0
        if (result.usage) {
          hasUsage = true
          const usage = result.usage
          if (usage.cost_usd === null || usage.cost_usd === undefined) allCostsProvided = false
          else totalCost += Number(usage.cost_usd)
          if (usage.prompt_tokens === null || usage.prompt_tokens === undefined) promptReported = false
          else promptTokens += Number(usage.prompt_tokens)
          if (usage.completion_tokens === null || usage.completion_tokens === undefined) completionReported = false
          else completionTokens += Number(usage.completion_tokens)
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

    await loadQueue()
    aiBusy.value = false
    aiCancelRequested.value = false
    aiProgress.value = null

    const outcome = 'Пакетов: ' + summary.completed + '/' + summary.total
      + '. Предложений: ' + summary.processed + '. Пропущено: ' + summary.skipped + '.'
    if (failure) {
      aiError.value = 'Остановка на ошибке: ' + failure.message + '. ' + outcome + ' Результаты успешных пакетов сохранены.'
    } else if (cancelled) {
      aiNotice.value = 'Остановлено после текущего запроса. ' + outcome
    } else {
      aiNotice.value = 'AI-классификация завершена. ' + outcome
    }
  }
}

function cancelClassification() {
  aiCancelRequested.value = true
}

onMounted(() => {
  void loadTags().catch(() => {})
  void loadQueue()
})
</script>

<template>
  <div class="grid gap-5">
    <Card class="min-w-0 p-5">
      <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <div>
          <h2 class="text-lg font-semibold">Очередь разметки</h2>
          <p class="text-xs text-muted-foreground">Треки, которым не хватает категорий. Отметьте нужные и примените теги массово.</p>
        </div>
        <div class="flex flex-wrap items-end gap-2">
          <label class="text-sm font-medium">
            Показать
            <Select
              :model-value="queue.untagged"
              aria-label="Показать треки"
              class="mt-2 block"
              @update:model-value="changeQueueMode($event)"
            >
              <option value="all">Без любых категорий</option>
              <option value="genre">Без жанров</option>
              <option value="mood">Без настроений</option>
            </Select>
          </label>
          <Button variant="outline" size="sm" :disabled="loading" @click="loadQueue"><RotateCcw :size="14" /> Обновить</Button>
          <Button ref="helpTrigger" variant="outline" size="sm" :aria-expanded="helpOpen" aria-controls="tagging-shortcuts" @click="toggleHelp">
            <Keyboard :size="15" aria-hidden="true" /> Горячие клавиши
          </Button>
        </div>
      </div>

      <div class="mb-4 rounded-lg border border-border bg-secondary p-3 text-sm" role="status">
        <span class="font-semibold">{{ total }}</span>
        <span class="text-muted-foreground"> треков {{ untaggedLabels[queue.untagged] }}</span>
      </div>

      <div v-if="error" role="alert" class="mb-4 rounded-lg border border-destructive/30 bg-destructive/10 p-3 text-sm text-destructive">
        {{ error }}
        <Button variant="ghost" size="sm" class="ml-2" @click="loadQueue">Повторить</Button>
      </div>

      <ListToolbar
        class="mb-4"
        :per-page="perPage"
        :sort="sort"
        :dir="dir"
        :compact="compact"
        :disabled="loading"
        @update:per-page="setPerPage"
        @update:sort="setSort"
        @update:dir="setDir"
        @toggle-compact="toggleCompact"
      />

      <p v-if="truncated" role="status" class="mb-3 rounded-lg border border-border bg-muted px-3 py-2 text-xs text-muted-foreground">
        Показаны первые {{ TRUNCATED_LIMIT }} треков по этому фильтру — сузьте фильтр, чтобы отсортировать всю очередь.
      </p>

      <BatchBar
        :selected-count="selected.length"
        :tag-id="batchTagId"
        :mode="batchMode"
        :busy="batch.busy.value"
        :classify-busy="aiBusy"
        :quick-tags="quickTags"
        @update:tag-id="batchTagId = $event"
        @update:mode="batchMode = $event"
        @apply="applyBatch"
        @clear="clearSelection"
        @classify="openClassifyPrompt(false)"
      />
      <p v-if="batch.error.value" role="alert" class="mb-4 text-sm text-destructive">{{ batch.error.value }}</p>
      <p v-else-if="batch.notice.value" role="status" class="mb-4 text-sm text-emerald-700">{{ batch.notice.value }}</p>

      <p v-if="shortcutHint" role="status" class="mb-3 text-xs text-muted-foreground">{{ shortcutHint }}</p>

      <div v-if="aiPreflight" class="mb-4 rounded-lg border border-border bg-muted p-4 text-sm" role="dialog" aria-label="Подтверждение AI-классификации">
        <div class="mb-2 flex items-center gap-2 font-semibold"><AlertTriangle :size="16" /> Платный запуск OpenRouter</div>
        <p class="mb-2 text-muted-foreground">
          Будут отправлены метаданные <strong>{{ selectedIds.length }}</strong> {{ trackWord }} — это
          <strong>{{ requestCount }}</strong> {{ requestWord }} (по 10 треков) к модели <strong>{{ model }}</strong>.
          Предварительная оценка стоимости: ≈ ${{ estimatedCost.toFixed(4) }} (точная сумма придёт в сводке после запуска).
        </p>
        <div class="flex gap-2">
          <Button size="sm" @click="runClassification"><Sparkles :size="15" /> Запустить ({{ requestCount }} {{ requestWord }})</Button>
          <Button variant="outline" size="sm" @click="aiPreflight = false">Отмена</Button>
        </div>
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

      <div v-if="aiError" role="alert" class="mb-4 rounded-lg border border-destructive/30 bg-destructive/10 p-3 text-sm text-destructive">{{ aiError }}</div>
      <p v-else-if="aiNotice" role="status" class="mb-4 text-sm text-muted-foreground">{{ aiNotice }}</p>

      <p v-if="aiUsage" class="mb-4 text-xs text-muted-foreground">
        OpenRouter: вход {{ aiUsage.prompt_tokens ?? 'н/д' }} токенов,
        выход {{ aiUsage.completion_tokens ?? 'н/д' }} токенов,
        стоимость {{ aiUsage.cost_usd === null ? 'не предоставлена' : '$' + aiUsage.cost_usd.toFixed(6) }}
      </p>

      <TrackList
        :rows="rows"
        :loading="loading"
        :compact="compact"
        :active-id="activeId"
        :error="Boolean(error)"
        selectable
        :selected="selected"
        @toggle-select="toggleSelected"
        @toggle-all="toggleAll"
        @activate="onRowActivate"
        @changed="loadQueue"
      >
        <template #empty>
          <p class="text-sm text-muted-foreground">
            <template v-if="total === 0">
              Очередь пуста: у всех треков есть категории по этому фильтру. Смените режим или вернитесь к референсам.
            </template>
            <template v-else>На этой странице ничего нет.</template>
          </p>
        </template>
      </TrackList>

      <div v-if="lastPage > 1" class="mt-6 flex flex-wrap items-center justify-between gap-3">
        <span class="text-xs text-muted-foreground">Страница {{ page }} из {{ lastPage }}</span>
        <div class="flex gap-2">
          <Button variant="outline" size="sm" :disabled="loading || page <= 1" @click="changePage(page - 1)"><ChevronLeft :size="16" /> Назад</Button>
          <Button variant="outline" size="sm" :disabled="loading || page >= lastPage" @click="changePage(page + 1)">Далее <ChevronRight :size="16" /></Button>
        </div>
      </div>
    </Card>

    <div v-if="helpOpen" class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 p-4 sm:items-center" @click.self="closeHelp()">
      <div
        ref="helpDialog"
        id="tagging-shortcuts"
        role="dialog"
        aria-modal="true"
        aria-labelledby="tagging-shortcuts-title"
        class="w-full max-w-lg rounded-xl border border-border bg-card p-5 shadow-lg"
        @keydown="trapDialogFocus"
      >
        <div class="mb-3 flex items-start justify-between gap-3">
          <h2 id="tagging-shortcuts-title" class="flex items-center gap-2 text-lg font-semibold">
            <Keyboard :size="18" aria-hidden="true" /> Горячие клавиши разметки
          </h2>
          <Button ref="helpCloseButton" variant="ghost" size="sm" aria-label="Закрыть справку по клавишам" @click="closeHelp()">
            <X :size="16" aria-hidden="true" />
          </Button>
        </div>
        <p class="mb-4 text-sm text-muted-foreground">
          Сокращения работают на этой странице, когда фокус не в поле ввода. Стрелки и Tab тоже работают как обычно.
        </p>
        <dl class="grid gap-2 text-sm">
          <div class="flex items-center justify-between gap-4 border-b border-border pb-2">
            <dt>Следующая / предыдущая строка</dt>
            <dd class="flex gap-1"><kbd class="rounded border border-border bg-muted px-2 py-0.5 font-mono text-xs">j</kbd><kbd class="rounded border border-border bg-muted px-2 py-0.5 font-mono text-xs">k</kbd></dd>
          </div>
          <div class="flex items-center justify-between gap-4 border-b border-border pb-2">
            <dt>Выбрать активную строку</dt>
            <dd><kbd class="rounded border border-border bg-muted px-2 py-0.5 font-mono text-xs">Пробел</kbd></dd>
          </div>
          <div class="flex items-center justify-between gap-4 border-b border-border pb-2">
            <dt>Категория для действия</dt>
            <dd class="flex gap-1"><kbd class="rounded border border-border bg-muted px-2 py-0.5 font-mono text-xs">1</kbd><span class="text-muted-foreground">–</span><kbd class="rounded border border-border bg-muted px-2 py-0.5 font-mono text-xs">9</kbd></dd>
          </div>
          <div class="flex items-center justify-between gap-4 border-b border-border pb-2">
            <dt>Применить к выбранным (или к активной строке)</dt>
            <dd><kbd class="inline-flex items-center gap-1 rounded border border-border bg-muted px-2 py-0.5 font-mono text-xs"><CornerDownLeft :size="12" aria-hidden="true" /> Enter</kbd></dd>
          </div>
          <div class="flex items-center justify-between gap-4">
            <dt>Открыть или закрыть эту справку</dt>
            <dd class="flex gap-1"><kbd class="rounded border border-border bg-muted px-2 py-0.5 font-mono text-xs">?</kbd><kbd class="rounded border border-border bg-muted px-2 py-0.5 font-mono text-xs">Esc</kbd></dd>
          </div>
        </dl>
        <div class="mt-4 flex justify-end">
          <Button size="sm" @click="closeHelp()">Понятно</Button>
        </div>
      </div>
    </div>
  </div>
</template>
