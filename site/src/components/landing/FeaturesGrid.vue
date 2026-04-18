<script setup lang="ts">
import {
  Beaker,
  Blocks,
  Gauge,
  ShieldCheck,
  Terminal,
  Waypoints,
} from 'lucide-vue-next'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'

interface Feature {
  icon: typeof Beaker
  title: string
  description: string
}

const features: Feature[] = [
  {
    icon: Beaker,
    title: 'Pest-native expectations',
    description:
      'Write evals as expect($agent)->toPassEval(...). Rubrics, JSON schemas, cost ceilings, and golden snapshots are all expect() extensions.',
  },
  {
    icon: Blocks,
    title: 'Assertion library',
    description:
      'Deterministic (Contains, Regex, Length, JsonSchema), operational (Cost, Token, Latency), and semantic (Rubric, Similar, Hallucination) assertions out of the box.',
  },
  {
    icon: Gauge,
    title: 'EvalRunner with OTel spans',
    description:
      'Every persisted run emits a proofread.eval.run span tree. Propagates to Jaeger, Tempo, Honeycomb, or any OTLP collector the host app configures.',
  },
  {
    icon: Terminal,
    title: 'Headless CLI subjects',
    description:
      'Evaluate against CLI tools like Claude Code under your flat-rate subscription. Token usage, cost, and model metadata are parsed from --output-format json.',
  },
  {
    icon: Waypoints,
    title: 'MCP tool evaluation',
    description:
      'Expose eval suites as MCP tools so MCP-compatible editors can list, run, diff, and compare providers without leaving the editor.',
  },
  {
    icon: ShieldCheck,
    title: 'Shadow evals in production',
    description:
      'Capture real traffic via middleware, evaluate asynchronously, alert on pass-rate drops, and promote failing captures into regression datasets.',
  },
]
</script>

<template>
  <section class="border-t border-border bg-surface-page py-20 md:py-24">
    <div class="container space-y-12">
      <div class="max-w-2xl space-y-3">
        <p class="font-mono text-sm uppercase tracking-wide text-brand-600 dark:text-brand-400">
          Features
        </p>
        <h2 class="text-balance text-3xl font-semibold tracking-tight md:text-4xl">
          Everything you need to ship confident AI features.
        </h2>
        <p class="text-lg text-muted-foreground">
          Proofread fills the gap between prototype and production, so
          you can measure whether your agents actually do what you
          think they do.
        </p>
      </div>

      <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        <Card
          v-for="feature in features"
          :key="feature.title"
          class="transition-shadow duration-base hover:shadow-md"
        >
          <CardHeader>
            <div
              class="mb-3 inline-flex h-10 w-10 items-center justify-center rounded-md bg-brand-500/10 text-brand-600 dark:text-brand-400"
            >
              <component :is="feature.icon" class="h-5 w-5" />
            </div>
            <CardTitle>{{ feature.title }}</CardTitle>
          </CardHeader>
          <CardContent>
            <CardDescription class="leading-relaxed">
              {{ feature.description }}
            </CardDescription>
          </CardContent>
        </Card>
      </div>
    </div>
  </section>
</template>
