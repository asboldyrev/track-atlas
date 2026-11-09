<script setup>
import { onMounted, ref } from 'vue'
import { LoaderCircle, Plus } from '@lucide/vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Card } from '@/components/ui/card'
import TagTranslationRow from '@/components/TagTranslationRow.vue'
import { useCatalog } from '@/composables/useCatalog'
import { useAsyncAction } from '@/composables/useAsyncAction'

const { genreTags, moodTags, fetchCatalog, mutate } = useCatalog()

const newTagName = ref('')
const newTagType = ref('genre')
const create = useAsyncAction()
const loading = ref(false)
const loadError = ref('')

async function loadTags() {
  loading.value = true
  loadError.value = ''
  try {
    await fetchCatalog('')
  } catch (e) {
    loadError.value = e.message
  } finally {
    loading.value = false
  }
}

async function addTag() {
  if (!newTagName.value.trim()) return
  const result = await create.run(
    () => mutate('/tags', 'POST', { name: newTagName.value.trim(), type: newTagType.value }),
    'Категория добавлена',
  )
  if (result.ok) {
    newTagName.value = ''
    await loadTags()
  }
}

onMounted(loadTags)
</script>

<template>
  <div class="grid items-start gap-5 lg:grid-cols-[320px_minmax(0,1fr)]">
    <Card class="p-5 lg:sticky lg:top-6">
      <h2 class="mb-1 font-semibold">Новая категория</h2>
      <p class="mb-4 text-sm text-muted-foreground">Категории — это жанры и настроения, по которым подбираются референсы.</p>
      <form @submit.prevent="addTag">
        <label class="mb-3 block text-sm font-medium">
          Тип
          <select
            v-model="newTagType"
            aria-label="Тип категории"
            class="mt-2 h-9 w-full rounded-md border border-input bg-background px-2 text-sm"
          >
            <option value="genre">Жанр</option>
            <option value="mood">Настроение</option>
          </select>
        </label>
        <label class="mb-3 block text-sm font-medium">
          Название
          <Input v-model="newTagName" placeholder="Например, Synthwave" class="mt-2" />
        </label>
        <div v-if="create.error.value" role="alert" class="mb-3 text-sm text-destructive">{{ create.error.value }}</div>
        <div v-if="create.notice.value" role="status" class="mb-3 text-sm text-emerald-700">{{ create.notice.value }}</div>
        <Button type="submit" class="w-full" :disabled="create.busy.value || !newTagName.trim()">
          <LoaderCircle v-if="create.busy.value" :size="15" class="animate-spin" />
          <Plus v-else :size="15" />
          Добавить категорию
        </Button>
      </form>
    </Card>

    <div class="grid gap-5" :aria-busy="loading ? 'true' : undefined">
      <div v-if="loadError" role="alert" class="rounded-lg border border-destructive/30 bg-destructive/10 p-3 text-sm text-destructive">
        {{ loadError }}
        <Button variant="ghost" size="sm" class="ml-2" @click="loadTags">Повторить</Button>
      </div>

      <Card v-for="group in [{ title: 'Жанры', tags: genreTags, hint: 'Жанр трека: используется как основной фильтр подбора.' }, { title: 'Настроения / Vibe', tags: moodTags, hint: 'Настроение трека: второй независимый фильтр.' }]" :key="group.title" class="p-5">
        <div class="mb-4 flex items-center justify-between">
          <div>
            <h2 class="text-lg font-semibold">{{ group.title }} <span class="text-muted-foreground">({{ group.tags.length }})</span></h2>
            <p class="text-xs text-muted-foreground">{{ group.hint }}</p>
          </div>
          <LoaderCircle v-if="loading" :size="18" class="animate-spin text-muted-foreground" aria-hidden="true" />
        </div>

        <div v-if="loading" role="status" aria-live="polite" aria-busy="true" class="space-y-2">
          <span class="sr-only">Загрузка категорий…</span>
          <span v-for="n in 3" :key="n" aria-hidden="true" class="block h-10 animate-pulse rounded bg-foreground/5"></span>
        </div>
        <p v-else-if="!group.tags.length" class="text-sm text-muted-foreground">Категорий этого типа пока нет. Добавьте первую слева.</p>
        <div v-else>
          <p class="mb-2 text-xs text-muted-foreground">Русское название можно изменить — оригинал и фильтры сохраняются.</p>
          <TagTranslationRow v-for="tag in group.tags" :key="tag.id" :tag="tag" @saved="loadTags" />
        </div>
      </Card>
    </div>
  </div>
</template>
