<script setup lang="ts">
import { BookOpen, Github, Moon, Sun, Palette } from 'lucide-vue-next'
import { RouterLink } from 'vue-router'
import { Button } from '@/components/ui/button'
import { useTheme } from '@/composables/useTheme'

const { theme, toggle } = useTheme()
</script>

<template>
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

      <nav class="flex items-center gap-1 text-sm">
        <RouterLink
          to="/docs"
          class="inline-flex h-9 items-center gap-1.5 rounded-md px-3 text-muted-foreground transition-colors duration-fast hover:bg-muted hover:text-foreground"
          active-class="bg-muted text-foreground"
        >
          <BookOpen class="h-4 w-4" />
          <span>Docs</span>
        </RouterLink>

        <RouterLink
          to="/primitives"
          class="inline-flex h-9 items-center gap-1.5 rounded-md px-3 text-muted-foreground transition-colors duration-fast hover:bg-muted hover:text-foreground"
          active-class="bg-muted text-foreground"
        >
          <Palette class="h-4 w-4" />
          <span>Primitives</span>
        </RouterLink>

        <a
          href="https://github.com/mosaiqo/proofread"
          target="_blank"
          rel="noopener"
          class="inline-flex h-9 items-center gap-1.5 rounded-md px-3 text-muted-foreground transition-colors duration-fast hover:bg-muted hover:text-foreground"
        >
          <Github class="h-4 w-4" />
          <span class="hidden sm:inline">GitHub</span>
        </a>

        <Button variant="ghost" size="icon" aria-label="Toggle theme" @click="toggle">
          <Sun v-if="theme === 'dark'" class="h-4 w-4" />
          <Moon v-else class="h-4 w-4" />
        </Button>
      </nav>
    </div>
  </header>
</template>
