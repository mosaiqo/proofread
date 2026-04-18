<script setup lang="ts">
import { Github } from 'lucide-vue-next'
</script>

<template>
  <footer class="mt-24 border-t border-border bg-surface-page">
    <div class="container flex flex-col gap-4 py-10 text-sm text-muted-foreground md:flex-row md:items-center md:justify-between">
      <div class="flex items-center gap-2">
        <span
          class="inline-flex h-6 w-6 items-center justify-center rounded bg-brand-500 font-mono text-xs font-bold text-brand-foreground"
          aria-hidden="true"
        >P</span>
        <span>Proofread &mdash; MIT licensed, built by <a class="text-foreground hover:underline" href="https://mosaiqo.com" target="_blank" rel="noopener">Mosaiqo</a>.</span>
      </div>

      <div class="flex items-center gap-5">
        <a
          href="https://packagist.org/packages/mosaiqo/proofread"
          target="_blank"
          rel="noopener"
          class="hover:text-foreground"
        >Packagist</a>
        <a
          href="https://github.com/mosaiqo/proofread/blob/main/CHANGELOG.md"
          target="_blank"
          rel="noopener"
          class="hover:text-foreground"
        >Changelog</a>
        <a
          href="https://github.com/mosaiqo/proofread"
          target="_blank"
          rel="noopener"
          class="inline-flex items-center gap-1.5 hover:text-foreground"
        >
          <Github class="h-4 w-4" />
          GitHub
        </a>
      </div>
    </div>
  </footer>
</template>
