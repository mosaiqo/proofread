<script setup lang="ts">
import { computed } from 'vue'
import { Moon, Monitor, Sun } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { useColorScheme } from '@/composables/useColorScheme'

const { mode, cycle } = useColorScheme()

const label = computed(() => {
  switch (mode.value) {
    case 'light':
      return 'Switch to dark theme'
    case 'dark':
      return 'Switch to system theme'
    default:
      return 'Switch to light theme'
  }
})

const title = computed(() => {
  switch (mode.value) {
    case 'light':
      return 'Theme: light'
    case 'dark':
      return 'Theme: dark'
    default:
      return 'Theme: system'
  }
})
</script>

<template>
  <Button
    variant="ghost"
    size="icon"
    :aria-label="label"
    :title="title"
    @click="cycle"
  >
    <Sun v-if="mode === 'light'" class="h-4 w-4" aria-hidden="true" />
    <Moon v-else-if="mode === 'dark'" class="h-4 w-4" aria-hidden="true" />
    <Monitor v-else class="h-4 w-4" aria-hidden="true" />
  </Button>
</template>
