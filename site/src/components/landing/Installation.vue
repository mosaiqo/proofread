<script setup lang="ts">
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import CodeBlock from '@/components/code/CodeBlock.vue'

const appInstall = `# 1. Install the package
composer require mosaiqo/proofread

# 2. Publish the config and migrations
php artisan vendor:publish --tag=proofread-config
php artisan vendor:publish --tag=proofread-migrations
php artisan migrate

# 3. (Optional) Expose eval suites to MCP-compatible editors
composer require laravel/mcp`

const appTest = `use App\\Agents\\SentimentAgent;
use Mosaiqo\\Proofread\\Assertions\\RegexAssertion;
use Mosaiqo\\Proofread\\Support\\Dataset;

require_once __DIR__.'/../vendor/mosaiqo/proofread/src/Testing/expectations.php';

it('classifies sentiment', function (): void {
    $dataset = Dataset::make('sentiment', [
        ['input' => 'I love this!', 'expected' => 'positive'],
    ]);

    expect(SentimentAgent::class)->toPassEval($dataset, [
        RegexAssertion::make('/^(positive|negative|neutral)$/'),
    ]);
});`

const appRun = `# Run your Pest eval suite
vendor/bin/pest

# Or drive a full EvalSuite from Artisan
php artisan evals:run "App\\Evals\\SentimentSuite"`

const packageDev = `# Clone + install
git clone https://github.com/mosaiqo/proofread.git
cd proofread
composer install

# The three gates that must pass on every commit
composer test        # Pest v4 against Testbench
composer analyse     # PHPStan
composer format      # Laravel Pint`
</script>

<template>
  <section id="installation" class="border-t border-border bg-surface-page py-20 md:py-24">
    <div class="container space-y-10">
      <div class="max-w-2xl space-y-3">
        <p class="font-mono text-sm uppercase tracking-wide text-brand-600 dark:text-brand-400">
          Installation
        </p>
        <h2 class="text-balance text-3xl font-semibold tracking-tight md:text-4xl">
          From zero to your first eval in three commands.
        </h2>
        <p class="text-lg text-muted-foreground">
          Requires PHP 8.4 and Laravel 13.x. Pest v4 recommended.
        </p>
      </div>

      <Tabs default-value="app" class="w-full">
        <TabsList>
          <TabsTrigger value="app">Laravel app</TabsTrigger>
          <TabsTrigger value="package">Package dev</TabsTrigger>
        </TabsList>

        <TabsContent value="app" class="space-y-4">
          <CodeBlock
            :code="appInstall"
            lang="bash"
            filename="1. Install"
          />
          <CodeBlock
            :code="appTest"
            lang="php"
            filename="2. Write your first Pest eval"
          />
          <CodeBlock
            :code="appRun"
            lang="bash"
            filename="3. Run it"
          />
        </TabsContent>

        <TabsContent value="package" class="space-y-4">
          <CodeBlock :code="packageDev" lang="bash" filename="Package development" />
        </TabsContent>
      </Tabs>
    </div>
  </section>
</template>
