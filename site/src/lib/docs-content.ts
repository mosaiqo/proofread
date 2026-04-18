import matter from 'gray-matter'
import type { NavItem, NavSection } from '@/types/docs'

const modules = import.meta.glob('@/content/docs/**/*.md', {
  query: '?raw',
  import: 'default',
  eager: false,
}) as Record<string, () => Promise<string>>

const ORDER_PREFIX = /^\d+[-_]/

function stripOrderPrefix(segment: string): string {
  return segment.replace(ORDER_PREFIX, '')
}

function rawSlugFromPath(path: string): string {
  const match = /\/content\/docs\/(.+)\.md$/.exec(path)
  if (!match) return ''
  return match[1].replace(/\/index$/, '').replace(/\/_/g, '/')
}

function publicSlugFromPath(path: string): string {
  const raw = rawSlugFromPath(path)
  if (!raw) return ''
  return raw
    .split('/')
    .map((segment) => stripOrderPrefix(segment))
    .join('/')
}

function titleFromSource(source: string, fallback: string): string {
  const match = /^#\s+(.+)$/m.exec(source)
  return match ? match[1].trim() : fallback
}

interface Frontmatter {
  title?: string
  section?: string
  order?: number
}

function parseFrontmatter(source: string): { data: Frontmatter; content: string } {
  const parsed = matter(source)
  return {
    data: parsed.data as Frontmatter,
    content: parsed.content,
  }
}

export interface DocEntry {
  slug: string
  rawSlug: string
  path: string
  load: () => Promise<string>
}

export function listDocs(): DocEntry[] {
  const out: DocEntry[] = []
  for (const [path, load] of Object.entries(modules)) {
    const rawSlug = rawSlugFromPath(path)
    const slug = publicSlugFromPath(path)
    if (!slug) continue
    out.push({ slug, rawSlug, path, load })
  }
  return out.sort((a, b) => a.rawSlug.localeCompare(b.rawSlug))
}

export async function loadDoc(slug: string): Promise<string | null> {
  const docs = listDocs()
  const doc = docs.find((d) => d.slug === slug)
  if (!doc) return null
  return doc.load()
}

let navCache: NavSection[] | null = null
let navPromise: Promise<NavSection[]> | null = null

export async function buildNavSections(): Promise<NavSection[]> {
  if (navCache) return navCache
  if (navPromise) return navPromise

  navPromise = (async () => {
    const docs = listDocs()
    const sources = await Promise.all(
      docs.map(async (doc) => {
        const source = await doc.load()
        const { data, content } = parseFrontmatter(source)
        const slugFallback = doc.slug
          .split('/')
          .pop()!
          .replace(/-/g, ' ')
          .replace(/\b\w/g, (c) => c.toUpperCase())
        const title = data.title ?? titleFromSource(content, slugFallback)
        return {
          slug: doc.slug,
          title,
          section: data.section ?? '',
        }
      }),
    )

    const buckets = new Map<string, NavItem[]>()
    const unsectioned: NavItem[] = []
    const sectionOrder: string[] = []

    for (const { slug, title, section } of sources) {
      const item: NavItem = { slug, title }
      if (!section) {
        unsectioned.push(item)
        continue
      }
      if (!buckets.has(section)) {
        buckets.set(section, [])
        sectionOrder.push(section)
      }
      buckets.get(section)!.push(item)
    }

    const out: NavSection[] = []
    for (const title of sectionOrder) {
      out.push({ title, items: buckets.get(title)! })
    }
    if (unsectioned.length > 0) {
      out.push({ title: 'Documentation', items: unsectioned })
    }

    navCache = out
    return out
  })()

  return navPromise
}

export function docExists(slug: string): boolean {
  return listDocs().some((d) => d.slug === slug)
}
