<script setup>
import { computed } from 'vue'
import ReferencesPage from '@/pages/ReferencesPage.vue'
import CategoriesPage from '@/pages/CategoriesPage.vue'
import TaggingPage from '@/pages/TaggingPage.vue'

// Multi-page shell: Laravel serves the same mount point for `/`, `/categories`
// and `/tagging`; the page is chosen from the real pathname, so navigation
// works with plain links, bookmarking and reloads.
const normalizedPath = window.location.pathname.replace(/\/+$/, '') || '/'
const pageByPath = { '/': 'references', '/categories': 'categories', '/tagging': 'tagging' }
const current = computed(() => pageByPath[normalizedPath] || 'not-found')

const navItems = [
  { href: '/', key: 'references', label: 'Референсы' },
  { href: '/categories', key: 'categories', label: 'Категории' },
  { href: '/tagging', key: 'tagging', label: 'Разметка' },
]

// Страница референсов — колонка 720 px: оболочка сужается до 768 px
// (720 + 2×24), чтобы шапка и колонка стояли на одной сетке.
const containerClass = computed(() => (current.value === 'references'
  ? 'mx-auto max-w-[768px] px-4 py-0 sm:px-6'
  : 'mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8'))
</script>

<template>
  <a
    href="#main"
    class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-primary focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:text-primary-foreground"
  >Перейти к содержимому</a>

  <div :class="containerClass">
    <header class="flex min-h-14 flex-nowrap items-center gap-x-6 gap-y-1 border-b border-border py-2.5 max-[359px]:flex-wrap max-[359px]:gap-x-3 max-[359px]:gap-y-0.5">
      <h1 class="shrink-0 whitespace-nowrap text-[15px] font-semibold leading-5 tracking-tight max-[359px]:leading-[18px]">Track Atlas</h1>
      <nav aria-label="Разделы" class="ml-auto flex gap-3 sm:gap-4 max-[359px]:ml-0">
        <a
          v-for="item in navItems"
          :key="item.key"
          :href="item.href"
          class="py-1.5 text-[13px] font-medium leading-4 transition-colors max-[359px]:leading-[13px]"
          :class="current === item.key ? 'text-foreground' : 'text-muted-foreground hover:text-foreground'"
          :aria-current="current === item.key ? 'page' : undefined"
        >{{ item.label }}</a>
      </nav>
    </header>

    <main v-if="current === 'references'" id="main" tabindex="-1" class="flex flex-col gap-4 pb-12 pt-6 focus:outline-none sm:gap-5 sm:pb-16 sm:pt-10 max-[359px]:pb-10 max-[359px]:pt-5">
      <ReferencesPage />
    </main>

    <main v-else id="main" tabindex="-1" class="pt-6 focus:outline-none">
      <CategoriesPage v-if="current === 'categories'" />
      <TaggingPage v-else-if="current === 'tagging'" />
      <div v-else class="rounded-xl border border-dashed border-border py-12 text-center text-sm text-muted-foreground">
        Страница не найдена. <a class="underline hover:text-foreground" href="/">Вернуться к референсам</a>.
      </div>
    </main>
  </div>
</template>
