import { mutate } from './useApi'

// AI-классификация и решения по предложениям. Эндпоинты остались на web-маршрутах
// (/ai/*) под CSRF; страница «Разметка» и строки списка зовут их отсюда, а не
// собирают запрос руками.
export function useSuggestions() {
  function classify(trackIds, { retryEmpty = false } = {}) {
    return mutate('/ai/classify', 'POST', { tracks: trackIds, retry_empty: retryEmpty === true })
  }

  function decide(suggestionId, decision) {
    return mutate('/ai/suggestions/' + suggestionId, 'PUT', { decision })
  }

  return { classify, decide }
}
