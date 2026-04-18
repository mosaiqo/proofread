import { computed } from 'vue'
import { useColorScheme } from '@/composables/useColorScheme'

/**
 * Legacy compatibility shim for components that only care about the
 * resolved light/dark value. Prefer `useColorScheme` for new code.
 */
export function useTheme() {
  const { effective, mode, setMode, cycle } = useColorScheme()

  const theme = computed(() => effective.value)

  function toggle(): void {
    setMode(effective.value === 'dark' ? 'light' : 'dark')
  }

  function setTheme(next: 'light' | 'dark'): void {
    setMode(next)
  }

  return { theme, toggle, setTheme, mode, cycle }
}
