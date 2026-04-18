<script setup lang="ts">
import { onMounted, ref } from 'vue'
import SidebarNav from '@/components/docs/SidebarNav.vue'
import TableOfContents from '@/components/docs/TableOfContents.vue'
import DocsSearch from '@/components/docs/DocsSearch.vue'
import { buildNavSections } from '@/lib/docs-content'
import type { NavSection, TocEntry } from '@/types/docs'

interface Props {
  toc?: TocEntry[]
}

withDefaults(defineProps<Props>(), {
  toc: () => [],
})

const sections = ref<NavSection[]>([])
const searchIndexUrl = `${import.meta.env.BASE_URL}search-index.json`

onMounted(async () => {
  sections.value = await buildNavSections()
})
</script>

<template>
  <div class="container grid gap-8 py-8 lg:grid-cols-[14rem_minmax(0,1fr)_12rem] lg:gap-10">
    <div class="lg:pt-2">
      <div class="mb-4 lg:mb-6">
        <DocsSearch :index-url="searchIndexUrl" />
      </div>
      <SidebarNav :sections="sections" />
    </div>

    <main id="docs-content" class="min-w-0">
      <slot />
    </main>

    <aside class="hidden lg:block lg:pt-2">
      <div class="sticky top-20">
        <TableOfContents :entries="toc" />
      </div>
    </aside>
  </div>
</template>
