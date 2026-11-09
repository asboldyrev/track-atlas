<script setup>
import { ArrowDownAZ, ArrowUpAZ, AlignJustify, Rows3 } from '@lucide/vue'
import { Button } from '@/components/ui/button'
import { Select } from '@/components/ui/select'
import { PAGE_SIZES, SORT_OPTIONS } from '@/composables/useTracks'

// A8: единая панель сортировки, размера страницы и плотности для списков.
// Все контролы подписаны видимым текстом — состояние не передаётся только цветом.
const props = defineProps({
  perPage: { type: Number, default: 30 },
  sort: { type: String, default: 'artist' },
  dir: { type: String, default: 'asc' },
  compact: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
})
const emit = defineEmits(['update:perPage', 'update:sort', 'update:dir', 'toggle-compact'])

const pageSizes = PAGE_SIZES
const sortOptions = SORT_OPTIONS
</script>

<template>
  <div class="flex flex-wrap items-end gap-x-4 gap-y-2 rounded-lg border border-border bg-muted/60 px-3 py-2">
    <label class="flex items-center gap-2 text-xs font-medium text-muted-foreground">
      Сортировка
      <Select
        :model-value="sort"
        class="h-8 text-xs"
        :disabled="disabled"
        @update:model-value="emit('update:sort', $event)"
      >
        <option v-for="option in sortOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
      </Select>
    </label>

    <Button
      variant="outline"
      size="sm"
      class="h-8"
      :disabled="disabled"
      :aria-label="dir === 'asc' ? 'Направление сортировки: по возрастанию. Переключить на убывание' : 'Направление сортировки: по убыванию. Переключить на возрастание'"
      :aria-pressed="dir === 'desc'"
      @click="emit('update:dir', dir === 'asc' ? 'desc' : 'asc')"
    >
      <ArrowDownAZ v-if="dir === 'asc'" :size="15" aria-hidden="true" />
      <ArrowUpAZ v-else :size="15" aria-hidden="true" />
      <span class="text-xs">{{ dir === 'asc' ? 'А→Я' : 'Я→А' }}</span>
    </Button>

    <label class="flex items-center gap-2 text-xs font-medium text-muted-foreground">
      На странице
      <Select
        :model-value="perPage"
        class="h-8 text-xs"
        :disabled="disabled"
        @update:model-value="emit('update:perPage', Number($event))"
      >
        <option v-for="size in pageSizes" :key="size" :value="size">{{ size }}</option>
      </Select>
    </label>

    <Button
      variant="outline"
      size="sm"
      class="ml-auto h-8"
      :disabled="disabled"
      :aria-pressed="compact"
      :aria-label="compact ? 'Плотность строк: компактная. Переключить на обычную' : 'Плотность строк: обычная. Переключить на компактную'"
      @click="emit('toggle-compact')"
    >
      <Rows3 v-if="compact" :size="15" aria-hidden="true" />
      <AlignJustify v-else :size="15" aria-hidden="true" />
      <span class="text-xs">{{ compact ? 'Компактно' : 'Обычная плотность' }}</span>
    </Button>
  </div>
</template>
