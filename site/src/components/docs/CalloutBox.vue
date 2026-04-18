<script setup lang="ts">
import { computed } from 'vue'
import { AlertCircle, AlertTriangle, CheckCircle2, Info } from 'lucide-vue-next'
import { cn } from '@/lib/utils'

type Variant = 'info' | 'warn' | 'danger' | 'success'

interface Props {
  variant?: Variant
  title?: string
}

const props = withDefaults(defineProps<Props>(), {
  variant: 'info',
})

const variantClasses: Record<Variant, string> = {
  info: 'border-info/30 bg-info/10 text-foreground',
  warn: 'border-warning/40 bg-warning/10 text-foreground',
  danger: 'border-destructive/40 bg-destructive/10 text-foreground',
  success: 'border-success/40 bg-success/10 text-foreground',
}

const iconClasses: Record<Variant, string> = {
  info: 'text-info',
  warn: 'text-warning',
  danger: 'text-destructive',
  success: 'text-success',
}

const defaultTitles: Record<Variant, string> = {
  info: 'Note',
  warn: 'Warning',
  danger: 'Danger',
  success: 'Tip',
}

const Icon = computed(() => {
  switch (props.variant) {
    case 'warn':
      return AlertTriangle
    case 'danger':
      return AlertCircle
    case 'success':
      return CheckCircle2
    default:
      return Info
  }
})

const resolvedTitle = computed(() => props.title ?? defaultTitles[props.variant])
</script>

<template>
  <aside
    :class="cn(
      'my-6 flex gap-3 rounded-lg border px-4 py-3 text-sm',
      variantClasses[props.variant],
    )"
    role="note"
  >
    <component :is="Icon" :class="cn('mt-0.5 h-5 w-5 flex-none', iconClasses[props.variant])" />
    <div class="min-w-0 flex-1">
      <p class="font-semibold leading-tight">{{ resolvedTitle }}</p>
      <div class="mt-1 text-foreground/90 [&>*:last-child]:mb-0 [&>p]:mb-2">
        <slot />
      </div>
    </div>
  </aside>
</template>
