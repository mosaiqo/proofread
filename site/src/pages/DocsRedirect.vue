<script setup lang="ts">
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()

onMounted(() => {
  router.replace('/docs/getting-started')
})
</script>

<template>
  <div class="container py-12 text-sm text-muted-foreground">Redirecting to documentation...</div>
</template>
