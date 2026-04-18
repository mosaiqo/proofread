<script setup lang="ts">
import { ref } from 'vue'
import { Check, Copy } from 'lucide-vue-next'
import { cn } from '@/lib/utils'

interface Props {
  text: string
  class?: string
  label?: string
}

const props = withDefaults(defineProps<Props>(), {
  label: 'Copy code',
})

const copied = ref(false)

async function copyToClipboard(): Promise<void> {
  try {
    await navigator.clipboard.writeText(props.text)
    copied.value = true
    setTimeout(() => (copied.value = false), 1500)
  } catch {
    // Clipboard may be blocked in insecure contexts; fail silently.
  }
}
</script>

<template>
  <button
    type="button"
    :aria-label="copied ? 'Copied' : label"
    :class="cn(
      'inline-flex h-8 items-center gap-1.5 rounded-md border border-border bg-card/80 px-2 text-xs text-muted-foreground backdrop-blur transition-colors duration-fast hover:bg-muted hover:text-foreground',
      props.class,
    )"
    @click="copyToClipboard"
  >
    <Check v-if="copied" class="h-4 w-4 text-success" />
    <Copy v-else class="h-4 w-4" />
    <span v-if="copied" class="font-medium">Copied!</span>
  </button>
</template>
