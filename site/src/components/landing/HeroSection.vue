<script setup lang="ts">
import { ArrowRight, Github } from 'lucide-vue-next'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import CodeBlock from '@/components/code/CodeBlock.vue'

const installSnippet = `composer require mosaiqo/proofread`
</script>

<template>
  <section class="relative overflow-hidden">
    <div
      class="pointer-events-none absolute inset-0 -z-10 bg-gradient-to-b from-brand-50/60 via-transparent to-transparent dark:from-brand-500/5"
      aria-hidden="true"
    />
    <div
      class="pointer-events-none absolute -top-32 left-1/2 -z-10 h-[400px] w-[800px] -translate-x-1/2 rounded-full bg-brand-500/10 blur-3xl"
      aria-hidden="true"
    />

    <div class="container grid gap-12 py-20 md:py-28 lg:grid-cols-[1.1fr_0.9fr] lg:items-center">
      <div class="space-y-8">
        <Badge variant="outline" class="gap-1.5">
          <span class="inline-block h-1.5 w-1.5 rounded-full bg-brand-500" />
          v0.9.0 &middot; pre-1.0, API unstable
        </Badge>

        <h1 class="text-balance text-4xl font-semibold leading-[1.05] tracking-tight md:text-5xl">
          The only eval package native to the official
          <span class="text-brand-500">Laravel AI</span> stack.
        </h1>

        <p class="max-w-xl text-pretty text-lg text-muted-foreground">
          Evaluate agents, prompts, and MCP tools from Pest, CI, and
          production. Pest-native expectations, shadow evals on real
          traffic, and a Laravel-first DX &mdash; no YAML DSL to learn.
        </p>

        <div class="flex flex-wrap items-center gap-3">
          <Button as="a" href="#installation" size="lg">
            Get started
            <ArrowRight class="h-4 w-4" />
          </Button>
          <Button
            as="a"
            href="https://github.com/mosaiqo/proofread"
            target="_blank"
            rel="noopener"
            variant="outline"
            size="lg"
          >
            <Github class="h-4 w-4" />
            View on GitHub
          </Button>
        </div>

        <dl class="flex flex-wrap items-center gap-x-8 gap-y-3 pt-4 text-sm text-muted-foreground">
          <div class="flex items-center gap-2">
            <dt class="font-mono text-xs uppercase tracking-wide">PHP</dt>
            <dd class="font-medium text-foreground">8.4</dd>
          </div>
          <div class="flex items-center gap-2">
            <dt class="font-mono text-xs uppercase tracking-wide">Laravel</dt>
            <dd class="font-medium text-foreground">13.x</dd>
          </div>
          <div class="flex items-center gap-2">
            <dt class="font-mono text-xs uppercase tracking-wide">Pest</dt>
            <dd class="font-medium text-foreground">v4</dd>
          </div>
          <div class="flex items-center gap-2">
            <dt class="font-mono text-xs uppercase tracking-wide">License</dt>
            <dd class="font-medium text-foreground">MIT</dd>
          </div>
        </dl>
      </div>

      <div class="space-y-3">
        <CodeBlock :code="installSnippet" lang="bash" filename="Terminal" />
        <CodeBlock
          lang="php"
          filename="tests/Feature/SentimentEval.php"
          :code="`expect(SentimentAgent::class)->toPassEval($dataset, [
    RegexAssertion::make('/^(positive|negative|neutral)$/'),
    Rubric::make('single lowercase sentiment label'),
    CostLimit::under(0.01),
]);`"
        />
      </div>
    </div>
  </section>
</template>
