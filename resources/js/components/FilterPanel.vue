<script setup>
import { Search, RotateCcw } from '@lucide/vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Checkbox } from '@/components/ui/checkbox'
import { Card } from '@/components/ui/card'
import { Select } from '@/components/ui/select'
import { useTags } from '@/composables/useTags'

defineProps({
  filters: { type: Object, required: true },
  genreTags: { type: Array, default: () => [] },
  moodTags: { type: Array, default: () => [] },
})
const emit = defineEmits(['search', 'toggle', 'untagged', 'reset', 'reload-tags'])

// loading/error справочника берём из общего composable: пока категории грузятся,
// список не должен выглядеть пустым («Пока нет жанров» — это про отсутствие
// данных, а не про процесс загрузки).
const { tagLabel, loading: tagsLoading, error: tagsError } = useTags()
</script>

<template>
  <Card class="p-5">
    <div class="mb-5 flex items-center justify-between">
      <h2 class="font-semibold">Фильтры</h2>
      <Button variant="ghost" size="sm" @click="emit('reset')"><RotateCcw :size="14" /> Сбросить</Button>
    </div>

    <label class="mb-4 block">
      <span class="mb-2 block text-sm font-medium">Поиск</span>
      <span class="relative block">
        <Search :size="16" class="pointer-events-none absolute left-3 top-2.5 text-muted-foreground" />
        <Input :model-value="filters.search" class="pl-9" placeholder="Трек или исполнитель" @update:model-value="emit('search', $event)" />
      </span>
    </label>

    <div v-if="tagsError" role="alert" class="mb-5 rounded-lg border border-destructive/30 bg-destructive/10 p-3 text-sm text-destructive">
      {{ tagsError }}
      <Button variant="ghost" size="sm" class="ml-2" @click="emit('reload-tags')">Повторить</Button>
    </div>

    <fieldset class="mb-5">
      <legend class="mb-2 text-sm font-semibold">Жанры</legend>
      <div v-if="tagsLoading" role="status" aria-live="polite" aria-busy="true" class="space-y-2">
        <span class="sr-only">Загрузка жанров…</span>
        <span v-for="n in 3" :key="n" aria-hidden="true" class="block h-4 animate-pulse rounded bg-foreground/10"></span>
      </div>
      <p v-else-if="!genreTags.length && !tagsError" class="text-sm text-muted-foreground">Пока нет жанров</p>
      <div v-else-if="genreTags.length" class="max-h-56 space-y-2 overflow-y-auto pr-1">
        <label v-for="tag in genreTags" :key="tag.id" class="flex cursor-pointer items-center gap-2 text-sm">
          <Checkbox
            :model-value="filters.genres.includes(tag.id)"
            :aria-label="'Жанр: ' + tagLabel(tag)"
            @update:model-value="emit('toggle', 'genres', tag.id, $event)"
          />
          <span>{{ tagLabel(tag) }}</span>
        </label>
      </div>
    </fieldset>

    <fieldset class="mb-5">
      <legend class="mb-2 text-sm font-semibold">Настроения / Vibe</legend>
      <div v-if="tagsLoading" role="status" aria-live="polite" aria-busy="true" class="space-y-2">
        <span class="sr-only">Загрузка настроений…</span>
        <span v-for="n in 3" :key="n" aria-hidden="true" class="block h-4 animate-pulse rounded bg-foreground/10"></span>
      </div>
      <p v-else-if="!moodTags.length && !tagsError" class="text-sm text-muted-foreground">Пока нет настроений</p>
      <div v-else-if="moodTags.length" class="max-h-56 space-y-2 overflow-y-auto pr-1">
        <label v-for="tag in moodTags" :key="tag.id" class="flex cursor-pointer items-center gap-2 text-sm">
          <Checkbox
            :model-value="filters.moods.includes(tag.id)"
            :aria-label="'Настроение: ' + tagLabel(tag)"
            @update:model-value="emit('toggle', 'moods', tag.id, $event)"
          />
          <span>{{ tagLabel(tag) }}</span>
        </label>
      </div>
    </fieldset>

    <label class="block text-sm font-medium">
      Полнота разметки
      <Select
        :model-value="filters.untagged"
        aria-label="Полнота разметки"
        class="mt-2 w-full"
        @update:model-value="emit('untagged', $event)"
      >
        <option value="">Все треки</option>
        <option value="genre">Без жанров</option>
        <option value="mood">Без настроений</option>
        <option value="all">Без любых категорий</option>
      </Select>
    </label>
  </Card>
</template>
