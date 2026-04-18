import { execFileSync } from 'node:child_process'
import { existsSync } from 'node:fs'
import { mkdir, readdir, writeFile } from 'node:fs/promises'
import { dirname, join, relative, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

/**
 * Builds `public/sitemap.xml` from the static routes and the markdown
 * files under `src/content/docs/`. Uses `git log` to back each `lastmod`
 * when available, falling back to today.
 */

const SITE_URL = 'https://mosaiqo.github.io/proofread'

const here = dirname(fileURLToPath(import.meta.url))
const siteRoot = resolve(here, '..')
const contentRoot = resolve(siteRoot, 'src', 'content', 'docs')
const outDir = resolve(siteRoot, 'public')
const outFile = resolve(outDir, 'sitemap.xml')

const today = new Date().toISOString().slice(0, 10)

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
  const trimmed = rel.replace(/\.md$/, '').replace(/\/index$/, '')
  return trimmed
    .split('/')
    .map((segment) => segment.replace(/^\d+[-_]/, ''))
    .join('/')
}

function lastModFor(path) {
  try {
    const raw = execFileSync('git', ['log', '-1', '--format=%aI', '--', path], {
      cwd: siteRoot,
      stdio: ['ignore', 'pipe', 'ignore'],
    })
      .toString()
      .trim()
    if (!raw) return today
    return raw.slice(0, 10)
  } catch {
    return today
  }
}

function escapeXml(value) {
  return value
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&apos;')
}

function renderUrl({ loc, lastmod, changefreq, priority }) {
  return [
    '  <url>',
    `    <loc>${escapeXml(loc)}</loc>`,
    `    <lastmod>${lastmod}</lastmod>`,
    `    <changefreq>${changefreq}</changefreq>`,
    `    <priority>${priority}</priority>`,
    '  </url>',
  ].join('\n')
}

async function build() {
  const urls = []

  urls.push({
    loc: `${SITE_URL}/`,
    lastmod: today,
    changefreq: 'weekly',
    priority: '1.0',
  })

  const files = await walk(contentRoot)
  files.sort()
  for (const file of files) {
    const slug = toSlug(file)
    if (!slug) continue
    urls.push({
      loc: `${SITE_URL}/docs/${slug}`,
      lastmod: lastModFor(file),
      changefreq: 'weekly',
      priority: '0.8',
    })
  }

  const body = [
    '<?xml version="1.0" encoding="UTF-8"?>',
    '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
    ...urls.map(renderUrl),
    '</urlset>',
    '',
  ].join('\n')

  if (!existsSync(outDir)) await mkdir(outDir, { recursive: true })
  await writeFile(outFile, body, 'utf8')
  const rel = relative(siteRoot, outFile)
  console.log(`Wrote ${rel} with ${urls.length} URLs.`)
}

build().catch((err) => {
  console.error('[build-sitemap] failed:', err)
  process.exit(1)
})
