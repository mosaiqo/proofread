<script setup lang="ts">
import { useHead } from '@unhead/vue'
import HeroSection from '@/components/landing/HeroSection.vue'
import FeaturesGrid from '@/components/landing/FeaturesGrid.vue'
import CodeExamples from '@/components/landing/CodeExamples.vue'
import Installation from '@/components/landing/Installation.vue'
import { OG_IMAGE, SITE_DESCRIPTION, SITE_URL } from '@/lib/seo'

const title = 'Proofread — Laravel-native AI evals'

useHead({
  title,
  link: [{ rel: 'canonical', href: `${SITE_URL}/` }],
  meta: [
    { name: 'description', content: SITE_DESCRIPTION },
    { property: 'og:title', content: title },
    { property: 'og:description', content: SITE_DESCRIPTION },
    { property: 'og:type', content: 'website' },
    { property: 'og:url', content: `${SITE_URL}/` },
    { property: 'og:image', content: OG_IMAGE },
    { name: 'twitter:card', content: 'summary_large_image' },
    { name: 'twitter:title', content: title },
    { name: 'twitter:description', content: SITE_DESCRIPTION },
    { name: 'twitter:image', content: OG_IMAGE },
  ],
})
</script>

<template>
  <HeroSection />
  <CodeExamples />
  <FeaturesGrid />
  <Installation />
</template>
