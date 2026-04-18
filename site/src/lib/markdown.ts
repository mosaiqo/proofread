import MarkdownIt from 'markdown-it'
import anchor from 'markdown-it-anchor'
import { getHighlighter, type SupportedLang } from '@/lib/shiki'
import type { TocEntry } from '@/types/docs'

const KNOWN_LANGS: ReadonlySet<SupportedLang> = new Set<SupportedLang>([
  'php',
  'blade-html',
  'bash',
  'yaml',
  'json',
  'ts',
  'vue',
  'markdown',
])

function resolveLangAlias(lang: string): string {
  switch (lang) {
    case 'ts':
    case 'typescript':
      return 'typescript'
    case 'blade-html':
    case 'blade':
      return 'blade'
    case 'shell':
    case 'sh':
      return 'bash'
    case 'js':
    case 'javascript':
      return 'javascript'
    default:
      return lang
  }
}

function slugify(input: string): string {
  return input
    .toLowerCase()
    .replace(/[^\p{Letter}\p{Number}\s-]+/gu, '')
    .trim()
    .replace(/\s+/g, '-')
}

export interface RenderedMarkdown {
  html: string
  toc: TocEntry[]
  title: string
}

let mdInstance: MarkdownIt | null = null

async function getRenderer(): Promise<MarkdownIt> {
  if (mdInstance) return mdInstance

  const highlighter = await getHighlighter()

  const md = new MarkdownIt({
    html: true,
    linkify: true,
    typographer: false,
    highlight(code, lang) {
      const resolved = resolveLangAlias(lang || '')
      if (!resolved) return ''
      try {
        return highlighter.codeToHtml(code, {
          lang: resolved,
          themes: {
            light: 'github-light',
            dark: 'github-dark',
          },
        })
      } catch {
        return ''
      }
    },
  })

  md.use(anchor, {
    slugify,
    permalink: anchor.permalink.linkInsideHeader({
      symbol: '#',
      placement: 'before',
      class: 'heading-anchor',
      ariaHidden: true,
    }),
  })

  mdInstance = md
  return md
}

function extractToc(source: string): TocEntry[] {
  const toc: TocEntry[] = []
  const lines = source.split('\n')
  let inFence = false
  for (const raw of lines) {
    const line = raw.trimEnd()
    if (/^\s*```/.test(line)) {
      inFence = !inFence
      continue
    }
    if (inFence) continue
    const match = /^(#{2,3})\s+(.+?)\s*$/.exec(line)
    if (!match) continue
    const level = match[1].length
    const text = match[2].replace(/`/g, '')
    toc.push({ id: slugify(text), text, level })
  }
  return toc
}

function extractTitle(source: string): string {
  const match = /^#\s+(.+)$/m.exec(source)
  return match ? match[1].trim() : ''
}

export async function renderMarkdown(source: string): Promise<RenderedMarkdown> {
  const md = await getRenderer()
  const html = md.render(source)
  return {
    html,
    toc: extractToc(source),
    title: extractTitle(source),
  }
}

export function __knownLangs(): ReadonlySet<SupportedLang> {
  return KNOWN_LANGS
}
