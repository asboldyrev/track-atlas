<script setup>
import { CheckCheck, LoaderCircle, Sparkles, X } from '@lucide/vue'
import { Button } from '@/components/ui/button'
import { useCatalog } from '@/composables/useCatalog'

const props = defineProps({
  selectedCount: { type: Number, default: 0 },
  tagId: { type: [String, Number], default: '' },
  mode: { type: String, default: 'add' },
  busy: { type: Boolean, default: false },
  classifyBusy: { type: Boolean, default: false },
})
const emit = defineEmits(['update:tagId', 'update:mode', 'apply', 'clear', 'classify'])

const { allTags, tagLabel } = useCatalog()
</script>

<template>
  <div class="mb-4 flex flex-wrap items-center gap-2 rounded-lg border border-border bg-muted p-3">
    <span class="inline-flex items-center gap-2 text-sm font-medium">
      <CheckCheck :size="18" class="text-muted-foreground" aria-hidden="true" />
      <template v-if="selectedCount">Выбрано: {{ selectedCount }}</template>
      <template v-else>Массовые действия</template>
    </span>

    <select
      :value="tagId"
      aria-label="Категория для массового действия"
      class="h-9 min-w-40 rounded-md border border-input bg-background px-2 text-sm"
      @change="emit('update:tagId', $event.target.value)"
    >
      <option value="">Выбрать категорию</option>
      <option v-for="tag in allTags" :key="tag.id" :value="tag.id">{{ tagLabel(tag) }}</option>
    </select>
    <select
      :value="mode"
      aria-label="Тип массового действия"
      class="h-9 rounded-md border border-input bg-background px-2 text-sm"
      @change="emit('update:mode', $event.target.value)"
    >
      <option value="add">Добавить</option>
      <option value="remove">Удалить</option>
    </select>
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
  </div>
</template>
