<script setup lang="ts">
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import {
  accentScale,
  brandScale,
  motionTokens,
  neutralScale,
  radiiScale,
  semanticTokens,
  shadowScale,
  spacingScale,
  typeScale,
  type TokenEntry,
} from '@/design/tokens'

function copy(token: TokenEntry): void {
  navigator.clipboard.writeText(`var(${token.var})`).catch(() => {})
}
</script>

<template>
  <div class="container space-y-20 py-16">
    <header class="max-w-2xl space-y-4">
      <Badge variant="outline">Design system</Badge>
      <h1 class="text-balance text-4xl font-semibold tracking-tight md:text-5xl">
        Visual-language primitives
      </h1>
      <p class="text-lg text-muted-foreground">
        Every surface of this site is built on a single CSS-variable
        catalog. Shadcn components, Tailwind utilities, and custom
        Vue components all resolve to the same tokens defined in
        <code class="rounded bg-muted px-1.5 py-0.5 text-sm">tokens.css</code>.
      </p>
    </header>

    <!-- Colors -->
    <section class="space-y-8">
      <div>
        <h2 class="text-2xl font-semibold tracking-tight">Color</h2>
        <p class="mt-1 text-muted-foreground">
          Brand red anchors the identity. Accent warms it. Neutrals
          carry a subtle warm tint to pair with the brand.
        </p>
      </div>

      <div class="space-y-6">
        <div>
          <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-muted-foreground">
            Brand
          </h3>
          <div class="grid grid-cols-5 gap-3 md:grid-cols-10">
            <button
              v-for="token in brandScale"
              :key="token.var"
              class="group text-left"
              @click="copy(token)"
            >
              <div
                class="mb-1.5 h-16 rounded-md shadow-sm ring-1 ring-inset ring-black/5 transition-transform duration-fast group-hover:scale-105"
                :style="{ backgroundColor: token.value }"
              />
              <div class="font-mono text-[11px] text-foreground">{{ token.name.split('/')[1] }}</div>
              <div class="font-mono text-[10px] text-muted-foreground">{{ token.value }}</div>
            </button>
          </div>
        </div>

        <div>
          <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-muted-foreground">
            Accent
          </h3>
          <div class="grid grid-cols-4 gap-3 md:grid-cols-8">
            <button
              v-for="token in accentScale"
              :key="token.var"
              class="group text-left"
              @click="copy(token)"
            >
              <div
                class="mb-1.5 h-16 rounded-md shadow-sm ring-1 ring-inset ring-black/5 transition-transform duration-fast group-hover:scale-105"
                :style="{ backgroundColor: token.value }"
              />
              <div class="font-mono text-[11px] text-foreground">{{ token.name.split('/')[1] }}</div>
              <div class="font-mono text-[10px] text-muted-foreground">{{ token.value }}</div>
            </button>
          </div>
        </div>

        <div>
          <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-muted-foreground">
            Neutral
          </h3>
          <div class="grid grid-cols-4 gap-3 md:grid-cols-11">
            <button
              v-for="token in neutralScale"
              :key="token.var"
              class="group text-left"
              @click="copy(token)"
            >
              <div
                class="mb-1.5 h-16 rounded-md shadow-sm ring-1 ring-inset ring-black/5 transition-transform duration-fast group-hover:scale-105"
                :style="{ backgroundColor: token.value }"
              />
              <div class="font-mono text-[11px] text-foreground">{{ token.name.split('/')[1] }}</div>
              <div class="font-mono text-[10px] text-muted-foreground">{{ token.value }}</div>
            </button>
          </div>
        </div>

        <div>
          <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-muted-foreground">
            Semantic
          </h3>
          <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
            <div
              v-for="token in semanticTokens"
              :key="token.var"
              class="rounded-md border border-border bg-card p-4"
            >
              <div
                class="mb-3 h-10 rounded"
                :style="{ backgroundColor: token.value }"
              />
              <div class="font-mono text-xs text-foreground">{{ token.name }}</div>
              <div class="font-mono text-[11px] text-muted-foreground">{{ token.var }}</div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Typography -->
    <section class="space-y-8">
      <div>
        <h2 class="text-2xl font-semibold tracking-tight">Typography</h2>
        <p class="mt-1 text-muted-foreground">
          Instrument Sans for UI, JetBrains Mono for code and numeric
          data. The scale grows roughly by 1.2&times; at display sizes.
        </p>
      </div>
      <div class="space-y-4">
        <div
          v-for="type in typeScale"
          :key="type.var"
          class="grid grid-cols-[80px_1fr_220px] items-baseline gap-4 border-b border-border-subtle pb-4"
        >
          <span class="font-mono text-xs text-muted-foreground">{{ type.name }}</span>
          <span
            class="truncate"
            :style="{ fontSize: `var(${type.var})`, lineHeight: 'var(--leading-snug)' }"
          >
            The quick brown fox jumps over the lazy dog
          </span>
          <span class="font-mono text-xs text-muted-foreground">
            {{ type.size }} &middot; {{ type.usage }}
          </span>
        </div>
      </div>
    </section>

    <!-- Spacing -->
    <section class="space-y-6">
      <div>
        <h2 class="text-2xl font-semibold tracking-tight">Spacing</h2>
        <p class="mt-1 text-muted-foreground">4px base scale. Use as margins, padding, or gaps.</p>
      </div>
      <div class="space-y-2">
        <div
          v-for="space in spacingScale"
          :key="space.var"
          class="grid grid-cols-[60px_1fr_180px] items-center gap-3 text-sm"
        >
          <span class="font-mono text-xs text-muted-foreground">{{ space.name }}</span>
          <div class="h-3 rounded-sm bg-brand-500" :style="{ width: space.value || '1px' }" />
          <span class="font-mono text-xs text-muted-foreground">
            {{ space.value || '0' }} &middot; {{ space.var }}
          </span>
        </div>
      </div>
    </section>

    <!-- Radii -->
    <section class="space-y-6">
      <div>
        <h2 class="text-2xl font-semibold tracking-tight">Radii</h2>
      </div>
      <div class="grid grid-cols-3 gap-4 md:grid-cols-6">
        <div
          v-for="radius in radiiScale"
          :key="radius.var"
          class="rounded-md border border-border bg-card p-4 text-center"
        >
          <div
            class="mx-auto mb-3 h-16 w-16 bg-brand-500"
            :style="{ borderRadius: `var(${radius.var})` }"
          />
          <div class="font-mono text-xs text-foreground">{{ radius.name }}</div>
          <div class="font-mono text-[11px] text-muted-foreground">{{ radius.value }}</div>
        </div>
      </div>
    </section>

    <!-- Shadows -->
    <section class="space-y-6">
      <div>
        <h2 class="text-2xl font-semibold tracking-tight">Elevation</h2>
        <p class="mt-1 text-muted-foreground">
          Three shadow tiers. All of them adjust automatically in dark mode.
        </p>
      </div>
      <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
        <div
          v-for="shadow in shadowScale"
          :key="shadow.var"
          class="rounded-lg border border-border bg-card p-8 text-center"
          :style="{ boxShadow: `var(${shadow.var})` }"
        >
          <div class="font-mono text-sm">shadow/{{ shadow.name }}</div>
          <div class="mt-1 font-mono text-xs text-muted-foreground">{{ shadow.var }}</div>
        </div>
      </div>
    </section>

    <!-- Motion -->
    <section class="space-y-6">
      <div>
        <h2 class="text-2xl font-semibold tracking-tight">Motion</h2>
      </div>
      <div class="grid grid-cols-2 gap-4 md:grid-cols-5">
        <div
          v-for="motion in motionTokens"
          :key="motion.var"
          class="rounded-md border border-border bg-card p-4"
        >
          <div class="font-mono text-xs text-foreground">{{ motion.name }}</div>
          <div class="mt-1 font-mono text-[11px] text-muted-foreground">{{ motion.value }}</div>
          <div class="mt-0.5 font-mono text-[10px] text-muted-foreground">{{ motion.var }}</div>
        </div>
      </div>
    </section>

    <!-- Components -->
    <section class="space-y-8">
      <div>
        <h2 class="text-2xl font-semibold tracking-tight">Components</h2>
        <p class="mt-1 text-muted-foreground">
          The shadcn-vue primitives used across the site, in every variant.
        </p>
      </div>

      <div class="space-y-8">
        <div class="space-y-4">
          <h3 class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">Buttons</h3>
          <div class="flex flex-wrap items-center gap-3">
            <Button>Default</Button>
            <Button variant="secondary">Secondary</Button>
            <Button variant="outline">Outline</Button>
            <Button variant="ghost">Ghost</Button>
            <Button variant="link">Link</Button>
            <Button variant="destructive">Destructive</Button>
          </div>
          <div class="flex flex-wrap items-center gap-3">
            <Button size="sm">Small</Button>
            <Button>Default</Button>
            <Button size="lg">Large</Button>
          </div>
        </div>

        <div class="space-y-4">
          <h3 class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">Badges</h3>
          <div class="flex flex-wrap items-center gap-3">
            <Badge>Default</Badge>
            <Badge variant="secondary">Secondary</Badge>
            <Badge variant="outline">Outline</Badge>
            <Badge variant="success">Success</Badge>
            <Badge variant="warning">Warning</Badge>
            <Badge variant="danger">Danger</Badge>
          </div>
        </div>

        <div class="space-y-4">
          <h3 class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">Card</h3>
          <Card class="max-w-md">
            <CardHeader>
              <CardTitle>EvalRun summary</CardTitle>
              <CardDescription>The anatomy of a persisted run.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-2 text-sm">
              <div class="flex justify-between"><span class="text-muted-foreground">Dataset</span><span class="font-mono">sentiment</span></div>
              <div class="flex justify-between"><span class="text-muted-foreground">Passed</span><span class="font-mono">28 / 30</span></div>
              <div class="flex justify-between"><span class="text-muted-foreground">Cost</span><span class="font-mono">$0.0048</span></div>
            </CardContent>
          </Card>
        </div>

        <div class="space-y-4">
          <h3 class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">Tabs</h3>
          <Tabs default-value="one" class="max-w-md">
            <TabsList>
              <TabsTrigger value="one">One</TabsTrigger>
              <TabsTrigger value="two">Two</TabsTrigger>
              <TabsTrigger value="three">Three</TabsTrigger>
            </TabsList>
            <TabsContent value="one">First panel content.</TabsContent>
            <TabsContent value="two">Second panel content.</TabsContent>
            <TabsContent value="three">Third panel content.</TabsContent>
          </Tabs>
        </div>
      </div>
    </section>
  </div>
</template>
