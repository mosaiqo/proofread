<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useHead } from '@unhead/vue'
import DocsLayout from '@/layouts/DocsLayout.vue'
import Pagination from '@/components/docs/Pagination.vue'
import { renderMarkdown } from '@/lib/markdown'
import { buildNavSections, loadDoc } from '@/lib/docs-content'
import { extractDescription, OG_IMAGE, SITE_DESCRIPTION, SITE_URL } from '@/lib/seo'
import type { NavItem, TocEntry } from '@/types/docs'

const route = useRoute()
const router = useRouter()

const html = ref<string>('')
const toc = ref<TocEntry[]>([])
const title = ref<string>('')
const description = ref<string>(SITE_DESCRIPTION)
const prev = ref<NavItem | null>(null)
const next = ref<NavItem | null>(null)
const notFound = ref(false)

const pageTitle = computed(() =>
  title.value ? `${title.value} — Proofread` : 'Documentation — Proofread',
)
const canonical = computed(() => `${SITE_URL}/docs/${slug.value}`)

useHead({
  title: pageTitle,
  link: [{ rel: 'canonical', href: canonical }],
  meta: [
    { name: 'description', content: description },
    { property: 'og:title', content: pageTitle },
    { property: 'og:description', content: description },
    { property: 'og:type', content: 'article' },
    { property: 'og:url', content: canonical },
    { property: 'og:image', content: OG_IMAGE },
    { name: 'twitter:card', content: 'summary_large_image' },
    { name: 'twitter:title', content: pageTitle },
    { name: 'twitter:description', content: description },
    { name: 'twitter:image', content: OG_IMAGE },
  ],
})

const slug = computed(() => {
  const raw = route.params.slug
  if (Array.isArray(raw)) return raw.join('/')
  return (raw ?? '') as string
})

async function load(): Promise<void> {
  notFound.value = false
  html.value = ''
  toc.value = []
  prev.value = null
  next.value = null

  const current = slug.value
  if (!current) {
    notFound.value = true
    return
  }

  try {
    const source = await loadDoc(current)
    if (!source) {
      console.warn(`[proofread/docs] No doc matches slug "${current}".`)
      notFound.value = true
      title.value = 'Page not found'
      description.value = SITE_DESCRIPTION
      return
    }

    const rendered = await renderMarkdown(source)
    html.value = rendered.html
    toc.value = rendered.toc
    title.value = rendered.title || current
    description.value = extractDescription(source) || SITE_DESCRIPTION

    const sections = await buildNavSections()
    const items: NavItem[] = sections.flatMap((s) => s.items)
    const index = items.findIndex((i) => i.slug === current)
    if (index > 0) prev.value = items[index - 1]
    if (index >= 0 && index < items.length - 1) next.value = items[index + 1]

    await nextTick()
    if (route.hash) {
      const el = document.querySelector(route.hash)
      if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' })
    }
  } catch (err) {
    console.error(`[proofread/docs] Failed to load doc "${current}":`, err)
    notFound.value = true
    title.value = 'Page not found'
  }
}

watch(
  () => route.fullPath,
  () => {
    if (route.name === 'docs-page') load()
  },
  { immediate: true },
)

function goHome(): void {
  router.push('/')
}
</script>

<template>
  <DocsLayout :toc="toc">
    <article v-if="!notFound && html" class="prose prose-neutral max-w-none dark:prose-invert">
      <div class="docs-article" v-html="html" />
      <Pagination :prev="prev" :next="next" />
    </article>

    <div v-else-if="notFound" class="py-12">
      <h1 class="text-2xl font-semibold">Page not found</h1>
      <p class="mt-2 text-muted-foreground">
        The docs page <code class="font-mono text-sm">{{ slug }}</code> does not exist yet.
      </p>
      <button
        type="button"
        class="mt-6 inline-flex h-9 items-center rounded-md bg-brand-500 px-4 text-sm font-medium text-brand-foreground transition-colors hover:bg-brand-600"
        @click="goHome"
      >
        Back to home
      </button>
    </div>

    <div v-else class="py-12 text-sm text-muted-foreground">Loading...</div>
  </DocsLayout>
</template>

<style>
.docs-article .heading-anchor {
  color: var(--color-fg-muted);
  text-decoration: none;
  margin-right: 0.35rem;
  opacity: 0;
  transition: opacity var(--duration-fast) var(--ease-out);
}

.docs-article h2:hover .heading-anchor,
.docs-article h3:hover .heading-anchor {
  opacity: 1;
}

.docs-article pre.shiki {
  margin: 1.25rem 0;
  padding: 1rem 1.25rem;
  border-radius: var(--radius-md);
  border: 1px solid var(--color-border);
  background: var(--color-bg-elevated) !important;
  overflow-x: auto;
  font-family: var(--font-mono);
  font-size: 0.875rem;
  line-height: 1.65;
}

.docs-article pre.shiki code {
  font-family: var(--font-mono);
  background: transparent;
  padding: 0;
}

.docs-article :not(pre) > code {
  font-family: var(--font-mono);
  font-size: 0.875em;
  padding: 0.1rem 0.35rem;
  border-radius: var(--radius-xs);
  background: var(--color-bg-muted);
  border: 1px solid var(--color-border-subtle);
}

.dark .docs-article pre.shiki,
.dark .docs-article pre.shiki span {
  color: var(--shiki-dark, inherit) !important;
  background-color: var(--shiki-dark-bg, var(--color-bg-elevated)) !important;
}

.docs-article .docs-callout {
  display: flex;
  gap: 0.75rem;
  margin: 1.5rem 0;
  padding: 0.75rem 1rem;
  border-radius: var(--radius-md);
  border: 1px solid;
  font-size: 0.9375rem;
  line-height: 1.6;
}

.docs-article .docs-callout__icon {
  flex: none;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.25rem;
  height: 1.25rem;
  margin-top: 0.25rem;
  border-radius: 9999px;
  font-family: var(--font-mono);
  font-size: 0.75rem;
  font-weight: 700;
  color: #fff;
}

.docs-article .docs-callout__body {
  flex: 1;
  min-width: 0;
}

.docs-article .docs-callout__title {
  margin: 0;
  font-weight: 600;
  line-height: 1.3;
}

.docs-article .docs-callout__content > *:first-child {
  margin-top: 0.25rem;
}

.docs-article .docs-callout__content > *:last-child {
  margin-bottom: 0;
}

.docs-article .docs-callout--info {
  border-color: color-mix(in srgb, var(--color-info, #3b82f6) 30%, transparent);
  background: color-mix(in srgb, var(--color-info, #3b82f6) 10%, transparent);
}

.docs-article .docs-callout--info .docs-callout__icon {
  background: var(--color-info, #3b82f6);
}

.docs-article .docs-callout--warn {
  border-color: color-mix(in srgb, var(--color-warning, #f59e0b) 40%, transparent);
  background: color-mix(in srgb, var(--color-warning, #f59e0b) 10%, transparent);
}

.docs-article .docs-callout--warn .docs-callout__icon {
  background: var(--color-warning, #f59e0b);
}

.docs-article .docs-callout--danger {
  border-color: color-mix(in srgb, var(--color-destructive, #ef4444) 40%, transparent);
  background: color-mix(in srgb, var(--color-destructive, #ef4444) 10%, transparent);
}

.docs-article .docs-callout--danger .docs-callout__icon {
  background: var(--color-destructive, #ef4444);
}

.docs-article .docs-callout--success {
  border-color: color-mix(in srgb, var(--color-success, #10b981) 40%, transparent);
  background: color-mix(in srgb, var(--color-success, #10b981) 10%, transparent);
}

.docs-article .docs-callout--success .docs-callout__icon {
  background: var(--color-success, #10b981);
}
</style>
