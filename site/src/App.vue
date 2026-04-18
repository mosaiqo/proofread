<script setup lang="ts">
import { RouterView } from 'vue-router'
import SiteHeader from '@/components/layout/SiteHeader.vue'
import SiteFooter from '@/components/layout/SiteFooter.vue'
import { useColorScheme } from '@/composables/useColorScheme'

useColorScheme()
</script>

<template>
  <div class="flex min-h-screen flex-col">
    <SiteHeader />
    <main id="main-content" class="flex-1" tabindex="-1">
      <RouterView />
    </main>
    <SiteFooter />
  </div>
</template>
