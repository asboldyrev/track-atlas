<script setup>
import { nextTick, onMounted, ref, watch } from 'vue'
import { Card } from '@/components/ui/card'
import FilterOption from '@/components/FilterOption.vue'
import MatchModeToggle from '@/components/MatchModeToggle.vue'
import { useTags } from '@/composables/useTags'

// Блок «Жанры» / «Настроения»: заголовок с переключателем режима и список
// значений-чекбоксов с внутренней прокруткой. Состояния (загрузка, ошибка,
// пустой справочник) показывает сам блок, чтобы сбой одного не блокировал
// другой блок и список треков.
const props = defineProps({
  headingId: { type: String, required: true },
  title: { type: String, required: true },
  tagList: { type: Array, default: () => [] },
  selected: { type: Array, default: () => [] },
  mode: { type: String, default: 'or' },
  modeLabel: { type: String, required: true },
  loading: { type: Boolean, default: false },
  error: { type: Boolean, default: false },
  loadingText: { type: String, required: true },
  errorText: { type: String, required: true },
  emptyText: { type: String, required: true },
})
defineEmits(['toggle', 'update:mode', 'retry'])

const { tagLabel } = useTags()
const listEl = ref(null)

// При восстановлении страницы из URL список прокручивается к первому
// выбранному значению, чтобы выбор не оказался за пределами видимых строк.
async function scrollToFirstSelected() {
  if (!props.selected.length) return
  await nextTick()
  listEl.value?.querySelector('[data-selected="true"]')?.scrollIntoView({ block: 'nearest' })
}

onMounted(scrollToFirstSelected)
watch(() => props.tagList.length, scrollToFirstSelected)
</script>

<template>
  <Card class="p-4 sm:p-5">
    <div class="mb-3 flex min-h-7 items-center justify-between gap-3">
      <h2 :id="headingId" class="text-sm font-semibold leading-5">{{ title }}</h2>
      <MatchModeToggle
        v-if="tagList.length > 1 || loading || error"
        :model-value="mode"
        :label="modeLabel"
        :disabled="loading || error"
        @update:model-value="$emit('update:mode', $event)"
      />
    </div>

    <div v-if="loading" role="status" aria-busy="true" class="flex flex-col gap-2.5 py-1">
      <span class="sr-only">{{ loadingText }}</span>
      <span
        v-for="width in ['38%', '52%', '31%']"
        :key="width"
        aria-hidden="true"
        class="block h-3.5 animate-pulse rounded bg-foreground/10 motion-reduce:animate-none"
        :style="{ width }"
      ></span>
    </div>

    <div
      v-else-if="error"
      role="alert"
      class="flex items-center gap-3 rounded-lg border border-destructive/30 bg-destructive/10 px-3.5 py-3 text-[13px] leading-[18px] text-destructive"
    >
      <span>{{ errorText }}</span>
      <button
        type="button"
        class="font-semibold underline underline-offset-4"
        @click="$emit('retry')"
      >Повторить</button>
    </div>

    <p v-else-if="!tagList.length" class="py-0.5 text-[13px] leading-[18px] text-muted-foreground">{{ emptyText }}</p>

    <div
      v-else
      ref="listEl"
      role="group"
      :aria-labelledby="headingId"
      class="grid max-h-56 grid-cols-1 gap-y-0 overflow-y-auto pr-1 [scrollbar-color:var(--border)_transparent] [scrollbar-width:thin] sm:grid-cols-2 sm:gap-x-6 sm:gap-y-0.5 [&::-webkit-scrollbar-thumb]:rounded [&::-webkit-scrollbar-thumb]:bg-border [&::-webkit-scrollbar-track]:bg-transparent [&::-webkit-scrollbar]:w-2"
    >
      <FilterOption
        v-for="tag in tagList"
        :key="tag.id"
        :data-selected="selected.includes(tag.id) ? 'true' : 'false'"
        :model-value="selected.includes(tag.id)"
        :label="tagLabel(tag)"
        @update:model-value="$emit('toggle', tag.id, $event)"
      />
    </div>
  </Card>
</template>
