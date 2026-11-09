import { ref, watch } from 'vue'

// Row density is a per-user visual preference, not a server concern: it is kept
// in localStorage and shared by every list page through module scope.
const STORAGE_KEY = 'track-atlas:density'

function readStored() {
  try {
    return window.localStorage.getItem(STORAGE_KEY) === 'compact'
  } catch {
    return false
  }
}

const compact = ref(readStored())

watch(compact, value => {
  try {
    window.localStorage.setItem(STORAGE_KEY, value ? 'compact' : 'comfortable')
  } catch {
    /* приватный режим браузера — просто не запоминаем */
  }
})

export function useDensity() {
  return {
    compact,
    toggleCompact: () => { compact.value = !compact.value },
  }
}
