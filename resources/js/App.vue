<script setup>
import { computed } from 'vue'
import { Music2 } from '@lucide/vue'
import { Badge } from '@/components/ui/badge'
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
</script>

<template>
  <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
    <header class="mb-6 flex flex-wrap items-center justify-between gap-4">
      <div class="flex items-center gap-3">
        <div class="rounded-xl bg-primary p-3 text-primary-foreground"><Music2 :size="25" /></div>
        <div>
          <h1 class="text-2xl font-semibold tracking-tight">Track Atlas</h1>
          <p class="text-sm text-muted-foreground">Музыкальные референсы по жанрам и настроениям</p>
        </div>
      </div>
      <Badge variant="outline" class="gap-2"><span class="size-2 rounded-full bg-emerald-500" aria-hidden="true"></span> Локальная библиотека</Badge>
    </header>

    <nav aria-label="Разделы" class="mb-6 flex flex-wrap gap-1 border-b border-border">
      <a
        v-for="item in navItems"
        :key="item.key"
        :href="item.href"
        class="rounded-t-md border-b-2 px-4 py-2 text-sm font-medium transition-colors"
        :class="current === item.key ? 'border-primary text-foreground' : 'border-transparent text-muted-foreground hover:text-foreground'"
        :aria-current="current === item.key ? 'page' : undefined"
      >{{ item.label }}</a>
    </nav>

    <main>
      <ReferencesPage v-if="current === 'references'" />
      <CategoriesPage v-else-if="current === 'categories'" />
      <TaggingPage v-else-if="current === 'tagging'" />
      <div v-else class="rounded-xl border border-dashed border-border py-12 text-center text-sm text-muted-foreground">
        Страница не найдена. <a class="underline hover:text-foreground" href="/">Вернуться к референсам</a>.
      </div>
    </main>
  </div>
</template>
