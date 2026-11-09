<script setup>
import { Checkbox } from '@/components/ui/checkbox'
import TrackRow from '@/components/TrackRow.vue'

const props = defineProps({
  rows: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  selectable: { type: Boolean, default: false },
  selected: { type: Array, default: () => [] },
})
const emit = defineEmits(['toggle-select', 'toggle-all', 'changed'])

function isSelected(id) {
  return props.selected.includes(id)
}

function allSelected() {
  return props.rows.length > 0 && props.selected.length === props.rows.length
}
</script>

<template>
  <div :aria-busy="loading ? 'true' : undefined">
    <div v-if="selectable && rows.length" class="mb-4 flex flex-wrap items-center gap-3 border-b border-border pb-4">
      <label class="flex cursor-pointer items-center gap-2 text-xs text-muted-foreground">
        <Checkbox
          :model-value="allSelected()"
          aria-label="Выделить все треки на странице"
          @update:model-value="emit('toggle-all', $event)"
        />
        Выделить все на странице
      </label>
      <span v-if="selected.length" class="text-xs font-medium">Выбрано: {{ selected.length }}</span>
    </div>

    <div v-if="loading" role="status" aria-live="polite" aria-busy="true" class="py-2">
      <span class="sr-only">Загрузка треков…</span>
      <div
        v-for="n in 4"
        :key="n"
        aria-hidden="true"
        class="border-b border-border py-4 last:border-0"
      >
        <div class="flex items-start gap-3">
          <div v-if="selectable" class="mt-1 size-4 shrink-0 animate-pulse rounded bg-foreground/10"></div>
          <div class="min-w-0 flex-1 space-y-2">
            <div class="h-4 animate-pulse rounded bg-foreground/10" :class="n % 2 ? 'w-2/5' : 'w-1/3'"></div>
            <div class="h-3 w-1/4 animate-pulse rounded bg-foreground/5"></div>
            <div class="flex gap-2 pt-1">
              <div class="h-5 w-16 animate-pulse rounded bg-foreground/5"></div>
              <div class="h-5 w-12 animate-pulse rounded bg-foreground/5"></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-else-if="!rows.length" class="rounded-xl border border-dashed border-border py-12 text-center">
      <slot name="empty" />
    </div>

    <TrackRow
      v-for="track in rows"
      v-else
      :key="track.id"
      :track="track"
      :selectable="selectable"
      :selected="isSelected(track.id)"
      @toggle-select="emit('toggle-select', track.id, $event)"
      @changed="emit('changed')"
    />
  </div>
</template>
