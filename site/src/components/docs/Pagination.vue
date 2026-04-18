<script setup lang="ts">
import { RouterLink } from 'vue-router'
import { ArrowLeft, ArrowRight } from 'lucide-vue-next'
import type { NavItem } from '@/types/docs'

interface Props {
  prev?: NavItem | null
  next?: NavItem | null
}

defineProps<Props>()
</script>

<template>
  <nav
    v-if="prev || next"
    class="mt-12 grid gap-3 border-t border-border pt-6 sm:grid-cols-2"
    aria-label="Pagination"
  >
    <RouterLink
      v-if="prev"
      :to="`/docs/${prev.slug}`"
      class="group flex flex-col rounded-lg border border-border bg-card px-4 py-3 text-sm transition-colors duration-fast hover:border-brand-500 hover:bg-muted sm:col-start-1"
    >
      <span class="flex items-center gap-1 text-xs text-muted-foreground">
        <ArrowLeft class="h-3 w-3" />
        Previous
      </span>
      <span class="mt-1 font-medium text-foreground">{{ prev.title }}</span>
    </RouterLink>
    <span v-else class="hidden sm:block" />

    <RouterLink
      v-if="next"
      :to="`/docs/${next.slug}`"
      class="group flex flex-col rounded-lg border border-border bg-card px-4 py-3 text-sm transition-colors duration-fast hover:border-brand-500 hover:bg-muted sm:col-start-2 sm:text-right"
    >
      <span class="flex items-center gap-1 text-xs text-muted-foreground sm:justify-end">
        Next
        <ArrowRight class="h-3 w-3" />
      </span>
      <span class="mt-1 font-medium text-foreground">{{ next.title }}</span>
    </RouterLink>
  </nav>
</template>
