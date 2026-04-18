<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import type { TocEntry } from '@/types/docs'
import { cn } from '@/lib/utils'

interface Props {
  entries: TocEntry[]
  containerSelector?: string
}

const props = withDefaults(defineProps<Props>(), {
  containerSelector: '#docs-content',
})

const activeId = ref<string>('')
let observer: IntersectionObserver | null = null

function disconnect(): void {
  if (observer) {
    observer.disconnect()
    observer = null
  }
}

async function observe(): Promise<void> {
  disconnect()
  await nextTick()
  if (typeof window === 'undefined') return
  const container = document.querySelector(props.containerSelector)
  if (!container) return

  const headings = Array.from(
    container.querySelectorAll<HTMLElement>('h2[id], h3[id]'),
  )
  if (headings.length === 0) return

  observer = new IntersectionObserver(
    (items) => {
      for (const item of items) {
        if (item.isIntersecting) {
          activeId.value = item.target.id
        }
      }
    },
    {
      rootMargin: '0px 0px -70% 0px',
      threshold: [0, 1],
    },
  )
  for (const heading of headings) observer.observe(heading)
}

onMounted(observe)
watch(() => props.entries, observe, { flush: 'post' })
onBeforeUnmount(disconnect)
</script>

<template>
  <nav v-if="entries.length > 0" aria-label="Table of contents" class="space-y-2 text-sm">
    <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
      On this page
    </p>
    <ul class="space-y-1 border-l border-border">
      <li v-for="entry in entries" :key="entry.id">
        <a
          :href="`#${entry.id}`"
          :class="cn(
            '-ml-px block border-l border-transparent py-0.5 pl-3 text-muted-foreground transition-colors duration-fast hover:text-foreground',
            entry.level === 3 && 'pl-6',
            activeId === entry.id && 'border-brand-500 text-foreground',
          )"
        >
          {{ entry.text }}
        </a>
      </li>
    </ul>
  </nav>
</template>
