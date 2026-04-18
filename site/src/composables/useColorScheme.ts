import { onBeforeUnmount, onMounted, ref, watch } from 'vue'

export type ColorSchemeMode = 'light' | 'dark' | 'system'
export type EffectiveScheme = 'light' | 'dark'

const STORAGE_KEY = 'proofread:color-scheme'
const LEGACY_STORAGE_KEY = 'proofread-theme'

const mode = ref<ColorSchemeMode>('system')
const effective = ref<EffectiveScheme>('light')
let initialized = false
let mediaQuery: MediaQueryList | null = null

function readStoredMode(): ColorSchemeMode {
  if (typeof localStorage === 'undefined') return 'system'
  try {
    const stored = localStorage.getItem(STORAGE_KEY)
    if (stored === 'light' || stored === 'dark' || stored === 'system') {
      return stored
    }
    const legacy = localStorage.getItem(LEGACY_STORAGE_KEY)
    if (legacy === 'light' || legacy === 'dark') return legacy
  } catch {
    // ignore
  }
  return 'system'
}

function prefersDark(): boolean {
  if (typeof window === 'undefined') return false
  return window.matchMedia('(prefers-color-scheme: dark)').matches
}

function resolveEffective(next: ColorSchemeMode): EffectiveScheme {
  if (next === 'system') return prefersDark() ? 'dark' : 'light'
  return next
}

function applyEffective(next: EffectiveScheme): void {
  if (typeof document === 'undefined') return
  const root = document.documentElement
  root.classList.toggle('dark', next === 'dark')
  root.style.colorScheme = next
}

function persist(next: ColorSchemeMode): void {
  if (typeof localStorage === 'undefined') return
  try {
    localStorage.setItem(STORAGE_KEY, next)
  } catch {
    // ignore quota / privacy-mode errors
  }
}

function initIfNeeded(): void {
  if (initialized) return
  initialized = true
  mode.value = readStoredMode()
  effective.value = resolveEffective(mode.value)
  applyEffective(effective.value)

  if (typeof window !== 'undefined') {
    mediaQuery = window.matchMedia('(prefers-color-scheme: dark)')
    const handler = (): void => {
      if (mode.value === 'system') {
        effective.value = resolveEffective('system')
        applyEffective(effective.value)
      }
    }
    mediaQuery.addEventListener('change', handler)
  }

  watch(mode, (next) => {
    effective.value = resolveEffective(next)
    applyEffective(effective.value)
    persist(next)
  })
}

export function useColorScheme() {
  onMounted(() => {
    initIfNeeded()
  })

  onBeforeUnmount(() => {
    // Global composable keeps listeners alive across component lifecycles.
  })

  function setMode(next: ColorSchemeMode): void {
    mode.value = next
  }

  function cycle(): void {
    mode.value =
      mode.value === 'light' ? 'dark' : mode.value === 'dark' ? 'system' : 'light'
  }

  return { mode, effective, setMode, cycle }
}
