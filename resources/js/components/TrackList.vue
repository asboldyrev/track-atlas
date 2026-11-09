<script setup>
import { LoaderCircle } from '@lucide/vue'
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
  <div>
    <div v-if="selectable && rows.length" class="mb-4 flex flex-wrap items-center gap-3 border-b border-border pb-4">
      <label class="flex cursor-pointer items-center gap-2 text-xs text-muted-foreground">
        <Checkbox :model-value="allSelected()" @update:model-value="emit('toggle-all', $event)" />
        Выделить все на странице
      </label>
      <span v-if="selected.length" class="text-xs font-medium">Выбрано: {{ selected.length }}</span>
    </div>

    <div v-if="loading" class="flex items-center gap-2 py-6 text-sm text-muted-foreground" role="status">
      <LoaderCircle :size="18" class="animate-spin" aria-hidden="true" /> Загрузка треков…
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
