import { readdir, readFile, writeFile, mkdir } from 'node:fs/promises'
import { existsSync } from 'node:fs'
import { dirname, join, relative, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

/**
 * Builds `public/search-index.json` from the markdown files under
 * `src/content/docs/`. Runs as part of `npm run build` (via `prebuild`)
 * so the shipped bundle always has a fresh index. No-ops gracefully
 * when there are no markdown files yet.
 */

const here = dirname(fileURLToPath(import.meta.url))
const siteRoot = resolve(here, '..')
const contentRoot = resolve(siteRoot, 'src', 'content', 'docs')
const outDir = resolve(siteRoot, 'public')
const outFile = resolve(outDir, 'search-index.json')

function slugify(input) {
  return input
    .toLowerCase()
    .replace(/[^\p{Letter}\p{Number}\s-]+/gu, '')
    .trim()
    .replace(/\s+/g, '-')
}

async function walk(dir) {
  const out = []
  if (!existsSync(dir)) return out
  const entries = await readdir(dir, { withFileTypes: true })
  for (const entry of entries) {
    const full = join(dir, entry.name)
    if (entry.isDirectory()) {
      out.push(...(await walk(full)))
    } else if (entry.isFile() && full.endsWith('.md')) {
      out.push(full)
    }
  }
  return out
}

function toSlug(path) {
  const rel = relative(contentRoot, path).replace(/\\/g, '/')
  return rel.replace(/\.md$/, '').replace(/\/index$/, '')
}

function parseSections(source) {
  const titleMatch = /^#\s+(.+)$/m.exec(source)
  const title = titleMatch ? titleMatch[1].trim() : ''
  const lines = source.split('\n')
  const sections = []

  let current = { heading: '', id: '', buffer: [] }
  let inFence = false

  for (const raw of lines) {
    const line = raw
    if (/^```/.test(line.trimStart())) {
      inFence = !inFence
      current.buffer.push(line)
      continue
    }
    if (inFence) {
      current.buffer.push(line)
      continue
    }
    const headingMatch = /^(#{2,3})\s+(.+?)\s*$/.exec(line)
    if (headingMatch) {
      if (current.buffer.length || current.heading) sections.push(current)
      const text = headingMatch[2].replace(/`/g, '')
      current = { heading: text, id: slugify(text), buffer: [] }
      continue
    }
    current.buffer.push(line)
  }
  if (current.buffer.length || current.heading) sections.push(current)

  return { title, sections }
}

function cleanBody(lines) {
  return lines
    .join('\n')
    .replace(/```[\s\S]*?```/g, ' ')
    .replace(/`([^`]+)`/g, '$1')
    .replace(/\[([^\]]+)\]\([^)]*\)/g, '$1')
    .replace(/^#.*$/gm, '')
    .replace(/\s+/g, ' ')
    .trim()
}

async function build() {
  const files = await walk(contentRoot)
  const entries = []

  for (const file of files) {
    const source = await readFile(file, 'utf8')
    const slug = toSlug(file)
    const { title, sections } = parseSections(source)
    const resolvedTitle = title || slug

    const body = cleanBody(sections.flatMap((s) => s.buffer))
    entries.push({ slug, title: resolvedTitle, body })

    for (const section of sections) {
      if (!section.heading) continue
      const sectionBody = cleanBody(section.buffer)
      if (!sectionBody && !section.heading) continue
      entries.push({
        slug,
        title: resolvedTitle,
        section: section.heading,
        sectionId: section.id,
        body: sectionBody,
      })
    }
  }

  if (!existsSync(outDir)) await mkdir(outDir, { recursive: true })
  await writeFile(outFile, JSON.stringify(entries, null, 2), 'utf8')
  const rel = relative(siteRoot, outFile)
  console.log(`Wrote ${rel} with ${entries.length} entries.`)
}

build().catch((err) => {
  console.error('[build-search-index] failed:', err)
  process.exit(1)
})
