export const SITE_URL = 'https://mosaiqo.github.io/proofread'
export const SITE_NAME = 'Proofread'
export const SITE_TAGLINE = 'Laravel-native AI evals'
export const SITE_DESCRIPTION =
  'The only eval package native to the official Laravel AI stack. Evaluate agents, prompts, and MCP tools from Pest, CI, and production.'
export const OG_IMAGE = `${SITE_URL}/og-image.png`

export interface SeoInput {
  title: string
  description?: string
  path: string
  type?: 'website' | 'article'
}

export interface ResolvedSeo {
  title: string
  description: string
  url: string
  image: string
  type: 'website' | 'article'
}

export function resolveSeo(input: SeoInput): ResolvedSeo {
  const url = `${SITE_URL}${input.path.startsWith('/') ? input.path : `/${input.path}`}`
  return {
    title: input.title,
    description: input.description?.trim() || SITE_DESCRIPTION,
    url,
    image: OG_IMAGE,
    type: input.type ?? 'website',
  }
}

export function extractDescription(markdown: string, maxLength = 180): string {
  const withoutFrontmatter = markdown.replace(/^---[\s\S]*?\n---\s*/, '')
  const lines = withoutFrontmatter.split('\n')
  let inFence = false
  const paragraph: string[] = []

  for (const raw of lines) {
    const line = raw.trim()
    if (/^```/.test(line)) {
      inFence = !inFence
      continue
    }
    if (inFence) continue
    if (!line) {
      if (paragraph.length > 0) break
      continue
    }
    if (/^#+\s/.test(line)) continue
    if (/^>\s/.test(line)) continue
    if (/^[-*+]\s/.test(line)) continue
    if (/^\|/.test(line)) continue
    paragraph.push(line)
  }

  let text = paragraph
    .join(' ')
    .replace(/\*\*([^*]+)\*\*/g, '$1')
    .replace(/\*([^*]+)\*/g, '$1')
    .replace(/`([^`]+)`/g, '$1')
    .replace(/\[([^\]]+)\]\([^)]+\)/g, '$1')
    .replace(/\s+/g, ' ')
    .trim()

  if (text.length > maxLength) {
    text = text.slice(0, maxLength - 1).replace(/\s+\S*$/, '') + '…'
  }
  return text
}
