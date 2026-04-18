import { onMounted, ref, watchEffect } from 'vue'

type Theme = 'light' | 'dark'

const STORAGE_KEY = 'proofread-theme'

const theme = ref<Theme>('light')

function applyTheme(next: Theme): void {
  const root = document.documentElement
  root.classList.toggle('dark', next === 'dark')
  root.style.colorScheme = next
}

export function useTheme() {
  onMounted(() => {
    const stored = localStorage.getItem(STORAGE_KEY) as Theme | null
    if (stored === 'light' || stored === 'dark') {
      theme.value = stored
    } else {
      theme.value = window.matchMedia('(prefers-color-scheme: dark)').matches
        ? 'dark'
        : 'light'
    }
  })

  watchEffect(() => {
    if (typeof document === 'undefined') return
    applyTheme(theme.value)
    try {
      localStorage.setItem(STORAGE_KEY, theme.value)
    } catch {
      // Ignore quota / privacy-mode errors; the class is already applied.
    }
  })

  function toggle(): void {
    theme.value = theme.value === 'dark' ? 'light' : 'dark'
  }

  function setTheme(next: Theme): void {
    theme.value = next
  }

  return { theme, toggle, setTheme }
}
