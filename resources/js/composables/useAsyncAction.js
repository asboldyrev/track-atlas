import { computed, ref } from 'vue'

// A4: every mutation keeps its own busy/error/notice so a failure in one block
// never turns into a page-wide banner and never freezes unrelated controls.
export function useAsyncAction() {
  const busy = ref(false)
  const error = ref('')
  const notice = ref('')

  function reset() {
    error.value = ''
    notice.value = ''
  }

  async function run(action, successMessage = '') {
    busy.value = true
    reset()
    try {
      const value = await action()
      if (successMessage) notice.value = successMessage
      return { ok: true, value }
    } catch (e) {
      error.value = e.message
      return { ok: false, error: e }
    } finally {
      busy.value = false
    }
  }

  const hasMessage = computed(() => Boolean(error.value || notice.value))

  return { busy, error, notice, hasMessage, reset, run }
}
