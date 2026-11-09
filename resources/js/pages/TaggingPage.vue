<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { ChevronLeft, ChevronRight, LoaderCircle, RotateCcw, Sparkles, AlertTriangle } from '@lucide/vue'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import BatchBar from '@/components/BatchBar.vue'
import TrackList from '@/components/TrackList.vue'
import { useCatalog, openRouterModel } from '@/composables/useCatalog'
import { useAsyncAction } from '@/composables/useAsyncAction'

const { fetchCatalog, mutate } = useCatalog()

const untaggedLabels = { genre: 'без жанров', mood: 'без настроений', all: 'без любых категорий' }

const queue = reactive({ untagged: 'all', page: 1 })
const rows = ref([])
const pagination = reactive({ total: 0, current_page: 1, last_page: 1 })
const loading = ref(false)
const error = ref('')
const selected = ref([])
let requestNumber = 0

const batchTagId = ref('')
const batchMode = ref('add')
const batch = useAsyncAction()

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

function makeQuery() {
  const params = new URLSearchParams()
  if (queue.untagged) params.set('untagged', queue.untagged)
  if (queue.page > 1) params.set('page', String(queue.page))
  return params.toString()
}

async function loadQueue() {
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
    selected.value = []
  } catch (e) {
    if (current === requestNumber) error.value = e.message
  } finally {
    if (current === requestNumber) loading.value = false
  }
}

function changeQueueMode(value) {
  queue.untagged = value
  queue.page = 1
  void loadQueue()
}

function changePage(next) {
  if (next < 1 || next > pagination.last_page || loading.value) return
  queue.page = next
  void loadQueue()
  window.scrollTo({ top: 0, behavior: 'smooth' })
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

async function applyBatch() {
  if (!selected.value.length || !batchTagId.value) return
  const result = await batch.run(
    () => mutate('/tracks/batch-tags', 'POST', {
      tracks: selected.value,
      tags: [Number(batchTagId.value)],
      mode: batchMode.value,
    }),
    'Изменено треков: ' + selected.value.length,
  )
  if (result.ok) await loadQueue()
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
        const result = await mutate('/ai/classify', 'POST', { tracks: batchIds, retry_empty: aiRetryEmpty.value === true })
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

onMounted(loadQueue)
</script>

<template>
  <div class="grid gap-5">
    <Card class="min-w-0 p-5">
      <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <div>
          <h2 class="text-lg font-semibold">Очередь разметки</h2>
          <p class="text-xs text-muted-foreground">Треки, которым не хватает категорий. Отметьте нужные и примените теги массово.</p>
        </div>
        <div class="flex items-end gap-2">
          <label class="text-sm font-medium">
            Показать
            <select :value="queue.untagged" class="mt-2 block h-9 rounded-md border border-input bg-background px-2 text-sm" @change="changeQueueMode($event.target.value)">
              <option value="all">Без любых категорий</option>
              <option value="genre">Без жанров</option>
              <option value="mood">Без настроений</option>
            </select>
          </label>
          <Button variant="outline" size="sm" :disabled="loading" @click="loadQueue"><RotateCcw :size="14" /> Обновить</Button>
        </div>
      </div>

      <div class="mb-4 rounded-lg border border-border bg-secondary p-3 text-sm" role="status">
        <span class="font-semibold">{{ pagination.total }}</span>
        <span class="text-muted-foreground"> треков {{ untaggedLabels[queue.untagged] }}</span>
      </div>

      <div v-if="error" role="alert" class="mb-4 rounded-lg border border-destructive/30 bg-destructive/10 p-3 text-sm text-destructive">
        {{ error }}
        <Button variant="ghost" size="sm" class="ml-2" @click="loadQueue">Повторить</Button>
      </div>

      <BatchBar
        :selected-count="selected.length"
        :tag-id="batchTagId"
        :mode="batchMode"
        :busy="batch.busy.value"
        :classify-busy="aiBusy"
        @update:tag-id="batchTagId = $event"
        @update:mode="batchMode = $event"
        @apply="applyBatch"
        @clear="clearSelection"
        @classify="openClassifyPrompt(false)"
      />
      <p v-if="batch.error.value" role="alert" class="mb-4 text-sm text-destructive">{{ batch.error.value }}</p>
      <p v-else-if="batch.notice.value" role="status" class="mb-4 text-sm text-emerald-700">{{ batch.notice.value }}</p>

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
        selectable
        :selected="selected"
        @toggle-select="toggleSelected"
        @toggle-all="toggleAll"
        @changed="loadQueue"
      >
        <template #empty>
          <p class="text-sm text-muted-foreground">
            <template v-if="pagination.total === 0">
              Очередь пуста: у всех треков есть категории по этому фильтру. Смените режим или вернитесь к референсам.
            </template>
            <template v-else>На этой странице ничего нет.</template>
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
