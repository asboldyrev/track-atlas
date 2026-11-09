import { computed, ref } from 'vue'
import { getJson, mutate } from './useApi'

// Справочник категорий и русские названия. Состояние в модуле: словарь нужен
// сразу нескольким страницам (фильтры, редактор тегов, массовые действия), и
// каждый, кто его загрузил, обновляет общий реактивный объект.
const genreTags = ref([])
const moodTags = ref([])
const loaded = ref(false)
const loading = ref(false)
const error = ref('')

const allTags = computed(() => [...genreTags.value, ...moodTags.value])

// Одна страница справочника = tags_per_page тегов; при переполнении догружаем
// следующие, чтобы клиент получил полный словарь.
async function fetchType(type) {
  const collected = []
  let page = 1
  let lastPage = 1
  do {
    const data = await getJson('/api/tags?type=' + type + '&page=' + page)
    const paginator = data.tags || {}
    collected.push(...(paginator.data || []))
    lastPage = paginator.last_page || 1
    page += 1
  } while (page <= lastPage)
  return collected
}

async function loadTags() {
  loading.value = true
  error.value = ''
  try {
    const results = await Promise.all([fetchType('genre'), fetchType('mood')])
    genreTags.value = results[0]
    moodTags.value = results[1]
    loaded.value = true
  } catch (e) {
    error.value = e.message
    throw e
  } finally {
    loading.value = false
  }
}

// Русское название — только из name_ru. Для свободного ярлыка (например, из
// предложения AI) пытаемся найти совпадающий тег справочника.
function translatedLabel(type, name, override = null) {
  const raw = String(name ?? '').trim()
  if (!raw) return raw
  let ru = override?.trim() || ''
  if (!ru) {
    const match = allTags.value.find(tag => tag.type === type && tag.name.toLowerCase() === raw.toLowerCase())
    ru = match?.name_ru?.trim() || ''
  }
  return ru && ru.toLowerCase() !== raw.toLowerCase() ? ru + ' (' + raw + ')' : raw
}

function tagLabel(tag) {
  return translatedLabel(tag.type, tag.name, tag.name_ru)
}

function createTag(name, type) {
  return mutate('/tags', 'POST', { name, type })
}

function saveTranslation(tagId, nameRu) {
  return mutate('/tags/' + tagId + '/translation', 'PUT', { name_ru: nameRu })
}

export function useTags() {
  return {
    genreTags, moodTags, allTags, loaded, loading, error,
    loadTags, createTag, saveTranslation, tagLabel, translatedLabel,
  }
}
