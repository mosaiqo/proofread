import { createHighlighterCore, type HighlighterCore } from 'shiki/core'
import { createOnigurumaEngine } from 'shiki/engine/oniguruma'

export type SupportedLang =
  | 'php'
  | 'blade-html'
  | 'bash'
  | 'yaml'
  | 'json'
  | 'ts'
  | 'vue'
  | 'markdown'

export type SupportedTheme = 'light' | 'dark'

/**
 * Singleton highlighter. Explicit imports keep the bundle small — without them
 * shiki ships every grammar and theme (~8MB of JS).
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
        import('@shikijs/langs/blade'),
        import('@shikijs/langs/html'),
        import('@shikijs/langs/bash'),
        import('@shikijs/langs/yaml'),
        import('@shikijs/langs/json'),
        import('@shikijs/langs/typescript'),
        import('@shikijs/langs/javascript'),
        import('@shikijs/langs/vue'),
        import('@shikijs/langs/markdown'),
      ],
      engine: createOnigurumaEngine(import('shiki/wasm')),
    })
  }
  return highlighterPromise
}

function resolveLang(lang: SupportedLang): string {
  switch (lang) {
    case 'ts':
      return 'typescript'
    case 'blade-html':
      return 'blade'
    default:
      return lang
  }
}

export async function getHighlighter(): Promise<HighlighterCore> {
  return loadHighlighter()
}

export async function highlight(
  code: string,
  lang: SupportedLang = 'php',
  theme: SupportedTheme = 'light',
): Promise<string> {
  const highlighter = await loadHighlighter()
  return highlighter.codeToHtml(code, {
    lang: resolveLang(lang),
    theme: theme === 'dark' ? 'github-dark' : 'github-light',
  })
}

export async function highlightDual(
  code: string,
  lang: SupportedLang = 'php',
): Promise<string> {
  const highlighter = await loadHighlighter()
  return highlighter.codeToHtml(code, {
    lang: resolveLang(lang),
    themes: {
      light: 'github-light',
      dark: 'github-dark',
    },
  })
}
