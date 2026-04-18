import type { NavItem, NavSection } from '@/types/docs'

const modules = import.meta.glob('@/content/docs/**/*.md', {
  query: '?raw',
  import: 'default',
  eager: false,
}) as Record<string, () => Promise<string>>

function slugFromPath(path: string): string {
  const match = /\/content\/docs\/(.+)\.md$/.exec(path)
  if (!match) return ''
  return match[1].replace(/\/index$/, '').replace(/\/_/g, '/')
}

function titleFromSource(source: string, fallback: string): string {
  const match = /^#\s+(.+)$/m.exec(source)
  return match ? match[1].trim() : fallback
}

export interface DocEntry {
  slug: string
  path: string
  load: () => Promise<string>
}

export function listDocs(): DocEntry[] {
  const out: DocEntry[] = []
  for (const [path, load] of Object.entries(modules)) {
    const slug = slugFromPath(path)
    if (!slug) continue
    out.push({ slug, path, load })
  }
  return out.sort((a, b) => a.slug.localeCompare(b.slug))
}

export async function loadDoc(slug: string): Promise<string | null> {
  const docs = listDocs()
  const doc = docs.find((d) => d.slug === slug)
  if (!doc) return null
  return doc.load()
}

export async function buildNavSections(): Promise<NavSection[]> {
  const docs = listDocs()
  const items: NavItem[] = []
  for (const doc of docs) {
    const source = await doc.load()
    const slugFallback = doc.slug
      .split('/')
      .pop()!
      .replace(/-/g, ' ')
      .replace(/\b\w/g, (c) => c.toUpperCase())
    items.push({
      slug: doc.slug,
      title: titleFromSource(source, slugFallback),
    })
  }
  if (items.length === 0) return []
  return [{ title: 'Documentation', items }]
}

export function docExists(slug: string): boolean {
  return listDocs().some((d) => d.slug === slug)
}
