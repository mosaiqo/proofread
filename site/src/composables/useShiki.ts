import { createHighlighterCore, type HighlighterCore } from 'shiki/core'
import { createOnigurumaEngine } from 'shiki/engine/oniguruma'

/**
 * Explicit imports keep the production bundle tiny. Without them,
 * shiki bundles every grammar and theme it ships — ~8MB of JS.
 */
let highlighterPromise: Promise<HighlighterCore> | null = null

function loadHighlighter(): Promise<HighlighterCore> {
  if (!highlighterPromise) {
    highlighterPromise = createHighlighterCore({
      themes: [
        import('@shikijs/themes/github-light'),
        import('@shikijs/themes/github-dark'),
      ],
      langs: [
        import('@shikijs/langs/php'),
        import('@shikijs/langs/bash'),
        import('@shikijs/langs/json'),
        import('@shikijs/langs/typescript'),
      ],
      engine: createOnigurumaEngine(import('shiki/wasm')),
    })
  }
  return highlighterPromise
}

export async function highlight(
  code: string,
  lang: 'php' | 'bash' | 'json' | 'ts' = 'php',
  theme: 'light' | 'dark' = 'light',
): Promise<string> {
  const highlighter = await loadHighlighter()
  const resolvedLang = lang === 'ts' ? 'typescript' : lang
  return highlighter.codeToHtml(code, {
    lang: resolvedLang,
    theme: theme === 'dark' ? 'github-dark' : 'github-light',
  })
}

export function useShiki() {
  return { highlight }
}
