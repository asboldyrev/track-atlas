import { computed, ref } from 'vue'
import { getJson, mutate } from './useApi'

// Справочник категорий и русские названия. Состояние в модуле: словарь нужен
// сразу нескольким страницам (фильтры, редактор тегов, массовые действия), и
// каждый, кто его загрузил, обновляет общий реактивный объект.
const genreTags = ref([])
const moodTags = ref([])
const loaded = ref(false)

// Загрузка и ошибка — по каждому типу отдельно: страница референсов показывает
// сбой одного справочника, не блокируя второй блок и список треков.
const genreLoading = ref(false)
const moodLoading = ref(false)
const genreError = ref('')
const moodError = ref('')

const loading = computed(() => genreLoading.value || moodLoading.value)
const error = computed(() => genreError.value || moodError.value)

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

// Загружает один тип справочника; промис не отклоняется, ошибка оседает в
// errorRef, чтобы упавший справочник не рвал загрузку второго.
async function loadType(type, target, loadingRef, errorRef) {
  loadingRef.value = true
  errorRef.value = ''
  try {
    target.value = await fetchType(type)
  } catch (e) {
    errorRef.value = e.message || 'Ошибка загрузки'
  } finally {
    loadingRef.value = false
  }
}

function reloadGenres() {
  return loadType('genre', genreTags, genreLoading, genreError)
}

function reloadMoods() {
  return loadType('mood', moodTags, moodLoading, moodError)
}

async function loadTags() {
  await Promise.all([reloadGenres(), reloadMoods()])
  loaded.value = true
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
    genreLoading, moodLoading, genreError, moodError,
    loadTags, reloadGenres, reloadMoods, createTag, saveTranslation, tagLabel, translatedLabel,
  }
}
