import { highlight, type SupportedLang, type SupportedTheme } from '@/lib/shiki'

export type { SupportedLang, SupportedTheme }

export { highlight }

export function useShiki() {
  return { highlight }
}
