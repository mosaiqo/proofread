<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { Check, Copy } from 'lucide-vue-next'
import { highlight } from '@/composables/useShiki'
import { useTheme } from '@/composables/useTheme'
import { cn } from '@/lib/utils'

interface Props {
  code: string
  lang?: 'php' | 'bash' | 'json' | 'ts'
  filename?: string
  copy?: boolean
  class?: string
}

const props = withDefaults(defineProps<Props>(), {
  lang: 'php',
  copy: true,
})

const { theme } = useTheme()
const html = ref<string>('')
const copied = ref(false)

const trimmed = computed(() => props.code.replace(/\n+$/, ''))

async function render(): Promise<void> {
  html.value = await highlight(trimmed.value, props.lang, theme.value)
}

onMounted(render)
watch([() => props.code, () => props.lang, theme], render)

async function copyToClipboard(): Promise<void> {
  try {
    await navigator.clipboard.writeText(trimmed.value)
    copied.value = true
    setTimeout(() => (copied.value = false), 1500)
  } catch {
    // Clipboard may be blocked in insecure contexts; fail silently.
  }
}
</script>

<template>
  <div
    :class="cn(
      'relative overflow-hidden rounded-lg border border-border bg-card text-sm shadow-sm',
      props.class,
    )"
  >
    <div
      v-if="filename"
      class="flex items-center justify-between border-b border-border bg-muted px-4 py-2"
    >
      <span class="font-mono text-xs text-muted-foreground">{{ filename }}</span>
      <span class="text-xs uppercase tracking-wide text-muted-foreground">{{ lang }}</span>
    </div>

    <button
      v-if="copy"
      type="button"
      class="absolute right-3 top-3 z-10 inline-flex h-8 w-8 items-center justify-center rounded-md border border-border bg-card/80 text-muted-foreground backdrop-blur transition-colors duration-fast hover:bg-muted hover:text-foreground"
      :aria-label="copied ? 'Copied' : 'Copy code'"
      @click="copyToClipboard"
    >
      <Check v-if="copied" class="h-4 w-4 text-success" />
      <Copy v-else class="h-4 w-4" />
    </button>

    <div v-if="html" class="shiki-wrapper overflow-x-auto" v-html="html" />
    <pre v-else class="overflow-x-auto p-4 text-sm"><code>{{ trimmed }}</code></pre>
  </div>
</template>

<style>
.shiki-wrapper pre.shiki {
  margin: 0;
  padding: 1rem 1.25rem;
  background: transparent !important;
  font-family: var(--font-mono);
  font-size: 0.875rem;
  line-height: 1.65;
}

.shiki-wrapper code {
  font-family: var(--font-mono);
}
</style>
