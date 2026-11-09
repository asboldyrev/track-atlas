// Общие помощники HTTP: единый разбор ответов, CSRF-заголовок для мутаций и
// чтение служебных мета-тегов страницы. Компоненты и composables не собирают
// заголовки сами — иначе одно и то же правило пришлось бы повторять везде.

// Сетевой сбой (fetch reject) приходит с техническим текстом браузера вроде
// «Failed to fetch». Пользователю он ничего не объясняет и не локализован,
// поэтому транспортные ошибки заменяем на понятное сообщение, а сообщение
// сервера (если оно есть) оставляем как есть.
const NETWORK_ERROR = 'Нет связи с сервером. Проверьте подключение и повторите попытку.'

async function request(url, options, fallbackMessage) {
  let response
  try {
    response = await fetch(url, options)
  } catch {
    throw new Error(NETWORK_ERROR)
  }

  let details = {}
  try {
    details = await response.json()
  } catch {
    details = {}
  }

  if (!response.ok) {
    throw new Error(details.message || fallbackMessage + ' (HTTP ' + response.status + ')')
  }
  return details
}

export function getJson(url) {
  return request(url, { headers: { Accept: 'application/json' } }, 'Не удалось загрузить данные')
}

export function mutate(url, method, body) {
  const token = document.querySelector('meta[name="csrf-token"]')?.content
  return request(url, {
    method,
    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token || '' },
    body: JSON.stringify(body),
  }, 'Ошибка сохранения')
}

export function openRouterModel() {
  return document.querySelector('meta[name="openrouter-model"]')?.content || 'OpenRouter'
}
