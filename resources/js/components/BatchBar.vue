<script setup>
import { CheckCheck, LoaderCircle, Sparkles, X } from '@lucide/vue'
import { Button } from '@/components/ui/button'
import { Select } from '@/components/ui/select'
import { useTags } from '@/composables/useTags'

const props = defineProps({
  selectedCount: { type: Number, default: 0 },
  tagId: { type: [String, Number], default: '' },
  mode: { type: String, default: 'add' },
  busy: { type: Boolean, default: false },
  classifyBusy: { type: Boolean, default: false },
  // A10: первые девять категорий доступны по цифрам 1–9 на /tagging.
  quickTags: { type: Array, default: () => [] },
})
const emit = defineEmits(['update:tagId', 'update:mode', 'apply', 'clear', 'classify'])

const { allTags, tagLabel } = useTags()
</script>

<template>
  <div class="mb-4 flex flex-wrap items-center gap-2 rounded-lg border border-border bg-muted p-3">
    <span class="inline-flex items-center gap-2 text-sm font-medium">
      <CheckCheck :size="18" class="text-muted-foreground" aria-hidden="true" />
      <template v-if="selectedCount">Выбрано: {{ selectedCount }}</template>
      <template v-else>Массовые действия</template>
    </span>

    <Select
      :model-value="tagId"
      aria-label="Категория для массового действия"
      class="min-w-40"
      @update:model-value="emit('update:tagId', $event)"
    >
      <option value="">Выбрать категорию</option>
      <option v-for="tag in allTags" :key="tag.id" :value="tag.id">{{ tagLabel(tag) }}</option>
    </Select>
    <Select
      :model-value="mode"
      aria-label="Тип массового действия"
      @update:model-value="emit('update:mode', $event)"
    >
      <option value="add">Добавить</option>
      <option value="remove">Удалить</option>
    </Select>
    <Button size="sm" :disabled="busy || !selectedCount || !tagId" @click="emit('apply')">
      <LoaderCircle v-if="busy" :size="16" class="animate-spin" />
      Применить
    </Button>

    <span class="mx-1 hidden h-6 w-px bg-border sm:block" aria-hidden="true"></span>

    <Button variant="outline" size="sm" :disabled="classifyBusy || !selectedCount" @click="emit('classify')">
      <Sparkles :size="16" /> AI: классифицировать выбранные
    </Button>
    <Button v-if="selectedCount" variant="ghost" size="sm" @click="emit('clear')"><X :size="15" /> Снять выделение</Button>

    <p v-if="!selectedCount" class="w-full text-xs text-muted-foreground">
      Отметьте треки галочками — действия станут активными. Работает со строками текущей страницы.
    </p>

    <div v-if="quickTags.length" class="flex w-full flex-wrap items-center gap-1.5 border-t border-border pt-2">
      <span class="text-xs text-muted-foreground">1–9:</span>
      <button
        v-for="(tag, index) in quickTags"
        :key="tag.id"
        type="button"
        class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs transition-colors"
        :class="String(tagId) === String(tag.id) ? 'border-primary bg-primary text-primary-foreground' : 'border-border bg-card text-foreground hover:bg-accent'"
        :aria-pressed="String(tagId) === String(tag.id)"
        :aria-label="'Выбрать категорию ' + tagLabel(tag) + ' (клавиша ' + (index + 1) + ')'"
        @click="emit('update:tagId', String(tag.id))"
      >
        <span class="rounded bg-foreground/10 px-1 font-mono text-[10px]" aria-hidden="true">{{ index + 1 }}</span>
        {{ tagLabel(tag) }}
      </button>
    </div>
  </div>
</template>
