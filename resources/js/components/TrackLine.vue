<script setup>
import { computed } from 'vue'
import { useTags } from '@/composables/useTags'

// Строка списка треков: только название, жанры и настроения. Исполнитель,
// альбом и действия сюда не выводятся — страница референсов, не редактор.
const props = defineProps({
  track: { type: Object, required: true },
})
const { tagLabel } = useTags()

const genres = computed(() => (props.track.tags || []).filter(tag => tag.type === 'genre'))
const moods = computed(() => (props.track.tags || []).filter(tag => tag.type === 'mood'))
</script>

<template>
  <li class="py-3.5 first:pt-0.5 last:pb-0.5">
    <h3 class="text-sm font-semibold leading-5 [overflow-wrap:anywhere]">{{ track.title }}</h3>
    <div v-if="genres.length || moods.length" class="mt-1.5 flex flex-wrap items-center gap-1.5">
      <span
        v-for="tag in genres"
        :key="'genre-' + tag.id"
        class="inline-flex items-center rounded-md bg-secondary px-1.5 py-0.5 text-xs leading-4 text-secondary-foreground"
      >
        <span class="sr-only">Жанр: </span>{{ tagLabel(tag) }}
      </span>
      <span
        v-for="tag in moods"
        :key="'mood-' + tag.id"
        class="inline-flex items-center rounded-md border border-border px-1.5 py-0.5 text-xs leading-4"
      >
        <span class="sr-only">Настроение: </span>{{ tagLabel(tag) }}
      </span>
    </div>
  </li>
</template>
