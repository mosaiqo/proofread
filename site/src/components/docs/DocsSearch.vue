<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { Search, X } from 'lucide-vue-next'
import {
  DialogContent,
  DialogOverlay,
  DialogPortal,
  DialogRoot,
  DialogTitle,
} from 'reka-ui'
import { hasIndex, loadIndex, queryIndex, type SearchHit } from '@/lib/search'
import { cn } from '@/lib/utils'

const props = defineProps<{
  indexUrl: string
}>()

const router = useRouter()
const open = ref(false)
const query = ref('')
const hits = ref<SearchHit[]>([])
const loading = ref(false)

async function ensureIndex(): Promise<void> {
  if (hasIndex()) return
  loading.value = true
  await loadIndex(props.indexUrl)
  loading.value = false
}

function handleKeydown(event: KeyboardEvent): void {
  const isMod = event.metaKey || event.ctrlKey
  if (isMod && event.key.toLowerCase() === 'k') {
    event.preventDefault()
    open.value = true
  }
}

watch(open, async (next) => {
  if (next) {
    await ensureIndex()
    query.value = ''
    hits.value = []
  }
})

watch(query, (value) => {
  hits.value = queryIndex(value, 10)
})

function goto(hit: SearchHit): void {
  const hash = hit.entry.sectionId ? `#${hit.entry.sectionId}` : ''
  open.value = false
  router.push(`/docs/${hit.entry.slug}${hash}`)
}

const hasResults = computed(() => hits.value.length > 0)

onMounted(() => {
  window.addEventListener('keydown', handleKeydown)
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleKeydown)
})
</script>

<template>
  <button
    type="button"
    class="inline-flex h-9 w-full max-w-xs items-center gap-2 rounded-md border border-border bg-card px-3 text-sm text-muted-foreground transition-colors duration-fast hover:border-brand-500 hover:text-foreground"
    aria-label="Search docs"
    @click="open = true"
  >
    <Search class="h-4 w-4" />
    <span class="flex-1 text-left">Search docs...</span>
    <kbd class="hidden items-center gap-0.5 rounded border border-border bg-muted px-1.5 py-0.5 font-mono text-[10px] sm:inline-flex">
      <span class="text-xs">⌘</span>K
    </kbd>
  </button>

  <DialogRoot v-model:open="open">
    <DialogPortal>
      <DialogOverlay
        class="fixed inset-0 z-modal bg-black/40 backdrop-blur-sm data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0"
      />
      <DialogContent
        class="fixed left-1/2 top-24 z-modal flex w-[90vw] max-w-xl -translate-x-1/2 flex-col overflow-hidden rounded-xl border border-border bg-card shadow-lg data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95"
      >
        <DialogTitle class="sr-only">Search documentation</DialogTitle>
        <div class="flex items-center gap-2 border-b border-border px-3 py-2">
          <Search class="h-4 w-4 text-muted-foreground" />
          <input
            v-model="query"
            type="text"
            placeholder="Search docs..."
            autofocus
            class="flex-1 bg-transparent py-1 text-sm outline-none placeholder:text-muted-foreground"
          />
          <button
            type="button"
            aria-label="Close search"
            class="inline-flex h-7 w-7 items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground"
            @click="open = false"
          >
            <X class="h-4 w-4" />
          </button>
        </div>
        <div class="max-h-[60vh] overflow-y-auto">
          <p v-if="loading" class="px-4 py-6 text-center text-sm text-muted-foreground">
            Loading index...
          </p>
          <p
            v-else-if="!query.trim()"
            class="px-4 py-6 text-center text-sm text-muted-foreground"
          >
            Start typing to search.
          </p>
          <p
            v-else-if="!hasResults"
            class="px-4 py-6 text-center text-sm text-muted-foreground"
          >
            No results for "{{ query }}".
          </p>
          <ul v-else class="divide-y divide-border">
            <li v-for="(hit, index) in hits" :key="`${hit.entry.slug}-${index}`">
              <button
                type="button"
                class="flex w-full flex-col items-start gap-0.5 px-4 py-3 text-left text-sm transition-colors duration-fast hover:bg-muted"
                @click="goto(hit)"
              >
                <span class="font-medium text-foreground">{{ hit.entry.title }}</span>
                <span v-if="hit.entry.section" class="text-xs text-muted-foreground">
                  {{ hit.entry.section }}
                </span>
              </button>
            </li>
          </ul>
        </div>
        <div :class="cn('border-t border-border px-3 py-2 text-xs text-muted-foreground')">
          Press <kbd class="rounded border border-border bg-muted px-1 font-mono">Esc</kbd> to close.
        </div>
      </DialogContent>
    </DialogPortal>
  </DialogRoot>
</template>
