// Общие помощники HTTP: единый разбор ответов, CSRF-заголовок для мутаций и
// чтение служебных мета-тегов страницы. Компоненты и composables не собирают
// заголовки сами — иначе одно и то же правило пришлось бы повторять везде.

export async function getJson(url) {
  const response = await fetch(url, { headers: { Accept: 'application/json' } })
  if (!response.ok) {
    const details = await response.json().catch(() => ({}))
    throw new Error(details.message || 'Не удалось загрузить данные (HTTP ' + response.status + ')')
  }
  return response.json()
}

export async function mutate(url, method, body) {
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

export function openRouterModel() {
  return document.querySelector('meta[name="openrouter-model"]')?.content || 'OpenRouter'
}
