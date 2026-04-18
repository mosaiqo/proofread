<script setup lang="ts">
import { useHead } from '@unhead/vue'
import { RouterLink } from 'vue-router'
import { AlertCircle, BookOpen, Home, Search } from 'lucide-vue-next'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import { SITE_DESCRIPTION, SITE_URL } from '@/lib/seo'

useHead({
  title: 'Page not found — Proofread',
  link: [{ rel: 'canonical', href: `${SITE_URL}/404` }],
  meta: [
    { name: 'description', content: SITE_DESCRIPTION },
    { name: 'robots', content: 'noindex' },
  ],
})

function openSearch(): void {
  const event = new KeyboardEvent('keydown', {
    key: 'k',
    code: 'KeyK',
    metaKey: true,
    ctrlKey: true,
    bubbles: true,
  })
  window.dispatchEvent(event)
}
</script>

<template>
  <DefaultLayout>
    <section class="container flex min-h-[60vh] items-center py-16">
      <div class="mx-auto max-w-xl text-center">
        <div class="mx-auto inline-flex h-16 w-16 items-center justify-center rounded-full bg-muted text-muted-foreground">
          <AlertCircle class="h-8 w-8" aria-hidden="true" />
        </div>

        <p class="mt-6 font-mono text-sm tracking-widest text-muted-foreground">
          404
        </p>
        <h1 class="mt-2 text-3xl font-semibold tracking-tight sm:text-4xl">
          Page not found
        </h1>
        <p class="mt-3 text-base text-muted-foreground">
          The page you&rsquo;re looking for doesn&rsquo;t exist or was moved.
        </p>

        <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
          <RouterLink
            to="/"
            class="inline-flex h-10 items-center gap-2 rounded-md bg-brand-500 px-4 text-sm font-medium text-brand-foreground shadow-sm transition-colors hover:bg-brand-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2"
          >
            <Home class="h-4 w-4" aria-hidden="true" />
            Back to home
          </RouterLink>

          <RouterLink
            to="/docs"
            class="inline-flex h-10 items-center gap-2 rounded-md border border-border bg-card px-4 text-sm font-medium text-foreground transition-colors hover:border-brand-500 hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2"
          >
            <BookOpen class="h-4 w-4" aria-hidden="true" />
            Browse docs
          </RouterLink>

          <button
            type="button"
            class="inline-flex h-10 items-center gap-2 rounded-md border border-border bg-card px-4 text-sm font-medium text-foreground transition-colors hover:border-brand-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2"
            @click="openSearch"
          >
            <Search class="h-4 w-4" aria-hidden="true" />
            Search
            <kbd class="ml-1 hidden items-center gap-0.5 rounded border border-border bg-muted px-1.5 py-0.5 font-mono text-[10px] sm:inline-flex">
              <span class="text-xs">⌘</span>K
            </kbd>
          </button>
        </div>
      </div>
    </section>
  </DefaultLayout>
</template>
