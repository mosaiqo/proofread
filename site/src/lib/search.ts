import { Document } from 'flexsearch'
import type { SearchEntry } from '@/types/docs'

export interface SearchHit {
  entry: SearchEntry
  score: number
}

interface IndexedEntry extends SearchEntry {
  id: number
  [key: string]: unknown
}

type DocIndex = InstanceType<typeof Document>

let index: DocIndex | null = null
let entries: IndexedEntry[] = []

export function buildIndex(source: SearchEntry[]): void {
  entries = source.map((entry, id) => ({ ...entry, id }))
  const doc = new Document({
    tokenize: 'forward',
    document: {
      id: 'id',
      index: ['title', 'section', 'body'],
      store: ['slug', 'title', 'section', 'sectionId', 'body'],
    },
  }) as DocIndex
  for (const entry of entries) {
    // `add` accepts any document shape at runtime; the types are overly strict.
    ;(doc.add as (entry: IndexedEntry) => void)(entry)
  }
  index = doc
}

export function queryIndex(query: string, limit = 10): SearchHit[] {
  if (!index || !query.trim()) return []
  const raw = index.search(query, { limit, enrich: true }) as Array<{
    field: string
    result: Array<number | { id: number }>
  }>
  const seen = new Set<number>()
  const hits: SearchHit[] = []
  for (const bucket of raw) {
    for (const item of bucket.result) {
      const id = typeof item === 'object' ? item.id : item
      if (seen.has(id)) continue
      seen.add(id)
      const entry = entries[id]
      if (entry) hits.push({ entry, score: 1 })
      if (hits.length >= limit) return hits
    }
  }
  return hits
}

export async function loadIndex(url: string): Promise<void> {
  try {
    const res = await fetch(url)
    if (!res.ok) {
      buildIndex([])
      return
    }
    const data = (await res.json()) as SearchEntry[]
    buildIndex(Array.isArray(data) ? data : [])
  } catch {
    buildIndex([])
  }
}

export function hasIndex(): boolean {
  return index !== null
}
