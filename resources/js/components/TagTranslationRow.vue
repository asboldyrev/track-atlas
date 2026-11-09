<script setup>
import { ref } from 'vue'
import { LoaderCircle } from '@lucide/vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { useCatalog } from '@/composables/useCatalog'
import { useAsyncAction } from '@/composables/useAsyncAction'

const props = defineProps({
  tag: { type: Object, required: true },
})
const emit = defineEmits(['saved'])

const { mutate } = useCatalog()
const value = ref(props.tag.name_ru || '')
const save = useAsyncAction()

async function submit() {
  const result = await save.run(
    () => mutate('/tags/' + props.tag.id + '/translation', 'PUT', { name_ru: value.value.trim() || null }),
    'Перевод сохранён',
  )
  if (result.ok) emit('saved')
}
</script>

<template>
  <div class="border-b border-border py-3 last:border-0">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
      <div class="min-w-0 sm:w-48">
        <p class="truncate text-sm font-medium">{{ tag.name }}</p>
        <p class="text-xs text-muted-foreground">{{ tag.slug }}</p>
      </div>
      <Input
        v-model="value"
        :aria-label="'Перевод на русский: ' + tag.name"
        placeholder="Перевод на русский"
        class="sm:flex-1"
        @keyup.enter="submit"
      />
      <Button size="sm" :disabled="save.busy.value" @click="submit">
        <LoaderCircle v-if="save.busy.value" :size="15" class="animate-spin" />
        Сохранить
      </Button>
    </div>
    <p v-if="save.error.value" role="alert" class="mt-2 text-xs text-destructive">{{ save.error.value }}</p>
    <p v-else-if="save.notice.value" role="status" class="mt-2 text-xs text-emerald-700">{{ save.notice.value }}</p>
  </div>
</template>
