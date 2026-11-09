import { computed, ref } from 'vue'

// Tags, translations and the JSON helpers are shared by every page of the
// single-page app, so the reactive state lives at module scope: whichever page
// loads the catalog updates the dictionary for all of them.
const genreTags = ref([])
const moodTags = ref([])
const tagTranslations = ref({ genre: {}, mood: {} })

const allTags = computed(() => [...genreTags.value, ...moodTags.value])

function applyTags(data) {
  genreTags.value = data.tags?.genre || []
  moodTags.value = data.tags?.mood || []
  tagTranslations.value = data.tag_translations || { genre: {}, mood: {} }
}

function translatedLabel(type, name, override = null) {
  const ru = override?.trim() || tagTranslations.value[type]?.[name.trim().toLocaleLowerCase()] || ''
  return ru && ru.toLocaleLowerCase() !== name.toLocaleLowerCase() ? ru + ' (' + name + ')' : name
}

function tagLabel(tag) {
  return translatedLabel(tag.type, tag.name, tag.name_ru)
}

async function fetchCatalog(query = '') {
  const response = await fetch('/catalog' + (query ? '?' + query : ''), {
    headers: { Accept: 'application/json' },
  })
  if (!response.ok) throw new Error('Не удалось загрузить каталог (HTTP ' + response.status + ')')
  const data = await response.json()
  applyTags(data)
  return data
}

async function mutate(url, method, body) {
  const token = document.querySelector('meta[name="csrf-token"]')?.content
  const response = await fetch(url, {
    method,
    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token || '' },
    body: JSON.stringify(body),
  })
  if (!response.ok) {
    const details = await response.json().catch(() => ({}))
    throw new Error(details.message || 'Ошибка сохранения (HTTP ' + response.status + ')')
  }
  return response.json()
}

export function useCatalog() {
  return { genreTags, moodTags, tagTranslations, allTags, fetchCatalog, mutate, tagLabel, translatedLabel }
}

export function openRouterModel() {
  return document.querySelector('meta[name="openrouter-model"]')?.content || 'OpenRouter'
}
