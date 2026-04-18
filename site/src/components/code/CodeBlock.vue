<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { highlight, type SupportedLang } from '@/lib/shiki'
import { useTheme } from '@/composables/useTheme'
import { cn } from '@/lib/utils'
import CopyButton from '@/components/code/CopyButton.vue'

interface Props {
  code: string
  lang?: SupportedLang
  filename?: string
  copy?: boolean
  lineNumbers?: boolean
  highlight?: string
  class?: string
}

const props = withDefaults(defineProps<Props>(), {
  lang: 'php',
  copy: true,
  lineNumbers: false,
})

const { theme } = useTheme()
const html = ref<string>('')

const trimmed = computed(() => props.code.replace(/\n+$/, ''))

const highlightedLines = computed<Set<number>>(() => {
  const result = new Set<number>()
  if (!props.highlight) return result
  for (const part of props.highlight.split(',')) {
    const piece = part.trim()
    if (!piece) continue
    const range = piece.split('-')
    if (range.length === 1) {
      const n = Number.parseInt(range[0], 10)
      if (!Number.isNaN(n)) result.add(n)
    } else if (range.length === 2) {
      const start = Number.parseInt(range[0], 10)
      const end = Number.parseInt(range[1], 10)
      if (!Number.isNaN(start) && !Number.isNaN(end)) {
        for (let i = start; i <= end; i++) result.add(i)
      }
    }
  }
  return result
})

async function render(): Promise<void> {
  const rendered = await highlight(trimmed.value, props.lang, theme.value)
  html.value = decorate(rendered)
}

function decorate(input: string): string {
  if (!props.lineNumbers && highlightedLines.value.size === 0) {
    return input
  }
  let lineIndex = 0
  return input.replace(/<span class="line">/g, () => {
    lineIndex += 1
    const classes = ['line']
    if (highlightedLines.value.has(lineIndex)) classes.push('line--highlight')
    const prefix = props.lineNumbers
      ? `<span class="line-number" aria-hidden="true">${lineIndex}</span>`
      : ''
    return `<span class="${classes.join(' ')}">${prefix}`
  })
}

onMounted(render)
watch(
  [() => props.code, () => props.lang, theme, () => props.highlight, () => props.lineNumbers],
  render,
)
</script>

<template>
  <div
    :class="cn(
      'group relative overflow-hidden rounded-lg border border-border bg-card text-sm shadow-sm',
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

    <CopyButton
      v-if="copy"
      :text="trimmed"
      class="absolute right-3 top-3 z-10 opacity-0 transition-opacity duration-fast group-hover:opacity-100 focus:opacity-100"
      :class="filename ? 'top-12' : 'top-3'"
    />

    <div
      v-if="html"
      :class="cn('shiki-wrapper overflow-x-auto', lineNumbers && 'shiki-wrapper--with-numbers')"
      v-html="html"
    />
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
  display: block;
}

.shiki-wrapper .line {
  display: inline-block;
  width: 100%;
  padding: 0 0.25rem;
}

.shiki-wrapper .line--highlight {
  background: color-mix(in oklab, var(--color-brand-500) 10%, transparent);
  box-shadow: inset 2px 0 0 var(--color-brand-500);
}

.shiki-wrapper--with-numbers .line-number {
  display: inline-block;
  width: 2rem;
  margin-right: 0.75rem;
  text-align: right;
  color: var(--color-fg-muted);
  user-select: none;
}
</style>
