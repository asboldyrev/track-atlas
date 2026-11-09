<script setup>
import { ref } from 'vue'
import { CheckCheck, LoaderCircle, Tags } from '@lucide/vue'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Badge } from '@/components/ui/badge'
import { useCatalog } from '@/composables/useCatalog'
import { useAsyncAction } from '@/composables/useAsyncAction'

const props = defineProps({
  track: { type: Object, required: true },
  selectable: { type: Boolean, default: false },
  selected: { type: Boolean, default: false },
})
const emit = defineEmits(['toggle-select', 'changed'])

const { genreTags, moodTags, tagLabel, translatedLabel, mutate } = useCatalog()

const editing = ref(false)
const editingTags = ref([])
const save = useAsyncAction()
const decide = useAsyncAction()
const decidingId = ref(null)

const pendingSuggestions = () => (props.track.metadata_suggestions || []).filter(s => s.status === 'pending')

function toggleEditor() {
  editing.value = !editing.value
  if (editing.value) {
    editingTags.value = props.track.tags.map(tag => tag.id)
    save.reset()
  }
}

function toggleEditTag(id, checked) {
  editingTags.value = checked ? [...new Set([...editingTags.value, id])] : editingTags.value.filter(v => v !== id)
}

async function saveTrack() {
  const result = await save.run(
    () => mutate('/tracks/' + props.track.id + '/tags', 'PUT', { tags: editingTags.value }),
    'Категории сохранены',
  )
  if (result.ok) {
    editing.value = false
    emit('changed')
  }
}

async function decideSuggestion(suggestion, decision) {
  decidingId.value = suggestion.id
  const result = await decide.run(
    () => mutate('/ai/suggestions/' + suggestion.id, 'PUT', { decision }),
    decision === 'accept' ? 'Предложенные теги применены' : 'Предложение отклонено',
  )
  decidingId.value = null
  if (result.ok) emit('changed')
}
</script>

<template>
  <div class="border-b border-border py-4 last:border-0">
    <div class="flex items-start gap-3">
      <Checkbox
        v-if="selectable"
        :model-value="selected"
        class="mt-1"
        :aria-label="'Выбрать трек: ' + (track.artist ? track.artist + ' — ' : '') + track.title"
        @update:model-value="emit('toggle-select', $event)"
      />
      <div class="min-w-0 flex-1">
        <h3 class="break-words text-sm font-semibold">{{ track.artist ? track.artist + ' — ' : '' }}{{ track.title }}</h3>
        <p v-if="track.album" class="mt-1 text-xs text-muted-foreground">{{ track.album }}</p>
        <div class="mt-2 flex flex-wrap gap-1.5">
          <Badge v-for="tag in track.tags" :key="tag.id" :variant="tag.type === 'mood' ? 'outline' : 'secondary'">{{ tagLabel(tag) }}</Badge>
          <span v-if="!track.tags.length" class="text-xs text-muted-foreground">Без категорий</span>
        </div>

        <div v-for="suggestion in pendingSuggestions()" :key="suggestion.id" class="mt-3 rounded-lg border border-border bg-muted/60 p-3 text-xs">
          <div class="mb-2 font-semibold">Предложение OpenRouter <span class="font-normal text-muted-foreground">({{ suggestion.model }})</span></div>
          <div class="mb-2 flex flex-wrap gap-1">
            <Badge v-for="genre in suggestion.genres || []" :key="'g' + genre" variant="secondary">{{ translatedLabel('genre', genre) }}</Badge>
            <Badge v-for="mood in suggestion.moods || []" :key="'m' + mood" variant="outline">{{ translatedLabel('mood', mood) }}</Badge>
            <span v-if="!(suggestion.genres || []).length && !(suggestion.moods || []).length" class="text-muted-foreground">Модель не смогла определить категории</span>
          </div>
          <div v-if="decide.error.value" role="alert" class="mb-2 text-destructive">{{ decide.error.value }}</div>
          <div v-if="decide.notice.value" role="status" class="mb-2 text-muted-foreground">{{ decide.notice.value }}</div>
          <div class="flex gap-2">
            <Button size="sm" :disabled="decide.busy.value" @click="decideSuggestion(suggestion, 'accept')">
              <LoaderCircle v-if="decidingId === suggestion.id" :size="14" class="animate-spin" />
              Принять
            </Button>
            <Button size="sm" variant="outline" :disabled="decide.busy.value" @click="decideSuggestion(suggestion, 'reject')">Отклонить</Button>
          </div>
        </div>
      </div>

      <Button
        variant="outline"
        size="sm"
        :aria-expanded="editing"
        @click="toggleEditor"
      >
        <Tags :size="15" /> {{ editing ? 'Закрыть' : 'Теги' }}
      </Button>
    </div>

    <div v-if="editing" class="ml-0 mt-4 rounded-lg border border-border bg-muted/50 p-4 sm:ml-7">
      <div v-for="(group, title) in { 'Жанры': genreTags, 'Настроения': moodTags }" :key="title" class="mb-4">
        <h4 class="mb-2 text-xs font-semibold">{{ title }}</h4>
        <p v-if="!group.length" class="text-xs text-muted-foreground">Категорий этого типа пока нет.</p>
        <div class="flex flex-wrap gap-x-4 gap-y-2">
          <label v-for="tag in group" :key="tag.id" class="flex cursor-pointer items-center gap-2 text-sm">
            <Checkbox :model-value="editingTags.includes(tag.id)" @update:model-value="toggleEditTag(tag.id, $event)" />
            {{ tagLabel(tag) }}
          </label>
        </div>
      </div>
      <div v-if="save.error.value" role="alert" class="mb-3 text-xs text-destructive">{{ save.error.value }}</div>
      <div v-if="save.notice.value" role="status" class="mb-3 text-xs text-muted-foreground">{{ save.notice.value }}</div>
      <Button size="sm" :disabled="save.busy.value" @click="saveTrack">
        <LoaderCircle v-if="save.busy.value" :size="15" class="animate-spin" />
        <CheckCheck v-else :size="15" />
        Сохранить категории
      </Button>
    </div>
  </div>
</template>
