<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import { Menu, X } from 'lucide-vue-next'
import type { NavSection } from '@/types/docs'
import { cn } from '@/lib/utils'

interface Props {
  sections: NavSection[]
}

defineProps<Props>()

const mobileOpen = ref(false)

function close(): void {
  mobileOpen.value = false
}
</script>

<template>
  <div>
    <button
      type="button"
      class="inline-flex h-9 items-center gap-2 rounded-md border border-border bg-card px-3 text-sm text-muted-foreground transition-colors duration-fast hover:bg-muted hover:text-foreground lg:hidden"
      :aria-expanded="mobileOpen"
      aria-controls="docs-sidebar"
      @click="mobileOpen = !mobileOpen"
    >
      <Menu v-if="!mobileOpen" class="h-4 w-4" />
      <X v-else class="h-4 w-4" />
      <span>Menu</span>
    </button>

    <aside
      id="docs-sidebar"
      :class="cn(
        'mt-4 space-y-6 lg:sticky lg:top-20 lg:mt-0 lg:block lg:max-h-[calc(100vh-5rem)] lg:overflow-y-auto lg:pr-2',
        mobileOpen ? 'block' : 'hidden',
      )"
    >
      <div v-if="sections.length === 0" class="text-sm text-muted-foreground">
        No docs yet.
      </div>

      <nav v-for="section in sections" :key="section.title" class="space-y-2">
        <p class="px-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
          {{ section.title }}
        </p>
        <ul class="space-y-0.5">
          <li v-for="item in section.items" :key="item.slug">
            <RouterLink
              :to="`/docs/${item.slug}`"
              class="block rounded-md px-2 py-1.5 text-sm text-muted-foreground transition-colors duration-fast hover:bg-muted hover:text-foreground"
              active-class="bg-muted text-foreground font-medium"
              @click="close"
            >
              {{ item.title }}
            </RouterLink>
          </li>
        </ul>
      </nav>
    </aside>
  </div>
</template>
