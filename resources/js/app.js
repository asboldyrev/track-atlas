import { createApp } from 'vue'
import CatalogApp from './App.vue'
import { loadMeta } from './composables/useTracks'

// Служебные лимиты (в т.ч. допустимые размеры страницы) читаем из /api/meta
// один раз при старте приложения, чтобы фронтенд не хардкодил числа.
loadMeta()

createApp(CatalogApp).mount('#app')
