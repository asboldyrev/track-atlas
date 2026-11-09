<script setup>
import { Search, RotateCcw } from '@lucide/vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Checkbox } from '@/components/ui/checkbox'
import { Card } from '@/components/ui/card'
import { useCatalog } from '@/composables/useCatalog'

defineProps({
  filters: { type: Object, required: true },
  genreTags: { type: Array, default: () => [] },
  moodTags: { type: Array, default: () => [] },
})
const emit = defineEmits(['search', 'toggle', 'untagged', 'reset'])

const { tagLabel } = useCatalog()
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

    <fieldset class="mb-5">
      <legend class="mb-2 text-sm font-semibold">Жанры</legend>
      <p v-if="!genreTags.length" class="text-sm text-muted-foreground">Пока нет жанров</p>
      <div class="max-h-56 space-y-2 overflow-y-auto pr-1">
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
      <p v-if="!moodTags.length" class="text-sm text-muted-foreground">Пока нет настроений</p>
      <div class="max-h-56 space-y-2 overflow-y-auto pr-1">
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
      <select
        :value="filters.untagged"
        aria-label="Полнота разметки"
        class="mt-2 h-9 w-full rounded-md border border-input bg-background px-2 text-sm"
        @change="emit('untagged', $event.target.value)"
      >
        <option value="">Все треки</option>
        <option value="genre">Без жанров</option>
        <option value="mood">Без настроений</option>
        <option value="all">Без любых категорий</option>
      </select>
    </label>
  </Card>
</template>
