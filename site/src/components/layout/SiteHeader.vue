<script setup lang="ts">
import { BookOpen, Github } from 'lucide-vue-next'
import { RouterLink } from 'vue-router'
import ColorSchemeToggle from '@/components/layout/ColorSchemeToggle.vue'
</script>

<template>
  <a
    href="#main-content"
    class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-modal focus:rounded-md focus:bg-brand-500 focus:px-3 focus:py-2 focus:text-sm focus:font-medium focus:text-brand-foreground"
  >
    Skip to main content
  </a>

  <header
    class="sticky top-0 z-sticky border-b border-border bg-surface-page/85 backdrop-blur"
  >
    <div class="container flex h-16 items-center justify-between">
      <RouterLink to="/" class="flex items-center gap-2 font-semibold tracking-tight">
        <span
          class="inline-flex h-8 w-8 items-center justify-center rounded-md bg-brand-500 font-mono text-sm font-bold text-brand-foreground shadow-sm"
          aria-hidden="true"
        >P</span>
        <span class="text-base">Proofread</span>
      </RouterLink>

      <nav class="flex items-center gap-1 text-sm" aria-label="Primary">
        <RouterLink
          to="/docs"
          class="inline-flex h-9 items-center gap-1.5 rounded-md px-3 text-muted-foreground transition-colors duration-fast hover:bg-muted hover:text-foreground"
          active-class="bg-muted text-foreground"
        >
          <BookOpen class="h-4 w-4" aria-hidden="true" />
          <span>Docs</span>
        </RouterLink>

        <a
          href="https://github.com/mosaiqo/proofread"
          target="_blank"
          rel="noopener"
          class="inline-flex h-9 items-center gap-1.5 rounded-md px-3 text-muted-foreground transition-colors duration-fast hover:bg-muted hover:text-foreground"
          aria-label="Proofread on GitHub (opens in a new tab)"
        >
          <Github class="h-4 w-4" aria-hidden="true" />
          <span class="hidden sm:inline">GitHub</span>
        </a>

        <ColorSchemeToggle />
      </nav>
    </div>
  </header>
</template>
