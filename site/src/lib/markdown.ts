import MarkdownIt from 'markdown-it'
import anchor from 'markdown-it-anchor'
import { parseFrontmatter } from '@/lib/frontmatter'
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

type CalloutVariant = 'info' | 'warn' | 'danger' | 'success'

const calloutTitle: Record<CalloutVariant, string> = {
  info: 'Note',
  warn: 'Warning',
  danger: 'Danger',
  success: 'Tip',
}

const calloutIcon: Record<CalloutVariant, string> = {
  info: 'i',
  warn: '!',
  danger: '!',
  success: '✓',
}

function calloutPlugin(md: MarkdownIt): void {
  md.core.ruler.after('block', 'callout_transform', (state) => {
    const tokens = state.tokens
    for (let i = 0; i < tokens.length; i++) {
      const token = tokens[i]
      if (token.type !== 'blockquote_open') continue

      let closeIdx = -1
      let depth = 1
      for (let j = i + 1; j < tokens.length; j++) {
        if (tokens[j].type === 'blockquote_open') depth++
        else if (tokens[j].type === 'blockquote_close') {
          depth--
          if (depth === 0) {
            closeIdx = j
            break
          }
        }
      }
      if (closeIdx === -1) continue

      const firstInline = tokens
        .slice(i + 1, closeIdx)
        .find((t) => t.type === 'inline')
      if (!firstInline) continue

      const variantMatch = /^\s*\*\*\[(info|warn|danger|success)\]\*\*\s*/i.exec(
        firstInline.content,
      )
      if (!variantMatch) continue

      const variant = variantMatch[1].toLowerCase() as CalloutVariant
      firstInline.content = firstInline.content.replace(variantMatch[0], '')
      if (firstInline.children && firstInline.children.length > 0) {
        const first = firstInline.children[0]
        if (first.type === 'text') {
          const stripped = first.content.replace(
            /^\s*\*\*\[(info|warn|danger|success)\]\*\*\s*/i,
            '',
          )
          first.content = stripped
        } else {
          const dropCount: number[] = []
          for (let k = 0; k < Math.min(firstInline.children.length, 4); k++) {
            const ch = firstInline.children[k]
            if (ch.type === 'strong_open' || ch.type === 'strong_close') {
              dropCount.push(k)
            } else if (ch.type === 'text') {
              const m = /^\s*\[(info|warn|danger|success)\]\s*/i.exec(ch.content)
              if (m) {
                ch.content = ch.content.replace(m[0], '')
                dropCount.push(k)
              }
            }
            if (dropCount.length >= 3) break
          }
          for (const idx of dropCount.reverse()) {
            firstInline.children.splice(idx, 1)
          }
        }
      }

      const openToken = tokens[i]
      const closeToken = tokens[closeIdx]
      openToken.type = 'html_block'
      openToken.tag = ''
      openToken.nesting = 0
      openToken.content = renderCalloutOpen(variant)
      openToken.block = true

      closeToken.type = 'html_block'
      closeToken.tag = ''
      closeToken.nesting = 0
      closeToken.content = '</div></aside>\n'
      closeToken.block = true
    }
    return false
  })
}

function renderCalloutOpen(variant: CalloutVariant): string {
  const title = calloutTitle[variant]
  const icon = calloutIcon[variant]
  return (
    `<aside class="docs-callout docs-callout--${variant}" role="note">` +
    `<span class="docs-callout__icon" aria-hidden="true">${icon}</span>` +
    `<div class="docs-callout__body">` +
    `<p class="docs-callout__title">${title}</p>` +
    `<div class="docs-callout__content">`
  )
}

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

  md.use(calloutPlugin)

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
  const parsed = parseFrontmatter<{ title?: string }>(source)
  const body = parsed.content
  const html = md.render(body)
  return {
    html,
    toc: extractToc(body),
    title: parsed.data.title ?? extractTitle(body),
  }
}

export function __knownLangs(): ReadonlySet<SupportedLang> {
  return KNOWN_LANGS
}
