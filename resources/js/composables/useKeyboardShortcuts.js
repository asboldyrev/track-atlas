import { onMounted, onUnmounted } from 'vue'

// A10: общий вход для клавиатурных сокращений.
// Главное правило — сокращения не перехватывают ввод: если фокус в поле ввода,
// в select, в textarea или в contenteditable, событие остаётся браузеру.
export function isTypingTarget(target) {
  if (!target || typeof target.tagName !== 'string') return false
  const tag = target.tagName.toUpperCase()
  if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return true
  return target.isContentEditable === true
}

export function useKeyboardShortcuts(handlers, { enabled = () => true } = {}) {
  function onKeydown(event) {
    if (!enabled()) return
    if (event.defaultPrevented) return
    if (event.ctrlKey || event.metaKey || event.altKey) return
    if (isTypingTarget(event.target)) return

    const handler = handlers[event.key]
    if (!handler) return
    if (handler(event) === false) return
    event.preventDefault()
  }

  onMounted(() => window.addEventListener('keydown', onKeydown))
  onUnmounted(() => window.removeEventListener('keydown', onKeydown))
}
