<script setup lang="ts">
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import CodeBlock from '@/components/code/CodeBlock.vue'

const pestExample = `use App\\Agents\\SentimentAgent;
use Mosaiqo\\Proofread\\Assertions\\CostLimit;
use Mosaiqo\\Proofread\\Assertions\\RegexAssertion;
use Mosaiqo\\Proofread\\Assertions\\Rubric;
use Mosaiqo\\Proofread\\Support\\Dataset;

it('classifies sentiment reliably and cheaply', function (): void {
    $dataset = Dataset::make('sentiment', [
        ['input' => 'I love this product!', 'expected' => 'positive'],
        ['input' => 'This is terrible.',    'expected' => 'negative'],
        ['input' => 'It works as described.', 'expected' => 'neutral'],
    ]);

    expect(SentimentAgent::class)->toPassEval($dataset, [
        RegexAssertion::make('/^(positive|negative|neutral)$/'),
        Rubric::make('response is a single lowercase sentiment label'),
        CostLimit::under(0.01),
    ]);
});`

const assertionExample = `use Mosaiqo\\Proofread\\Assertions\\ContainsAssertion;

$output = $agent->handle('Say hello in English');

expect($output)->toPassAssertion(
    ContainsAssertion::make('hello')
);`

const runnerExample = `use App\\Agents\\SentimentAgent;
use Mosaiqo\\Proofread\\Assertions\\RegexAssertion;
use Mosaiqo\\Proofread\\Runner\\EvalRunner;
use Mosaiqo\\Proofread\\Support\\Dataset;

$runner     = new EvalRunner();
$dataset    = Dataset::make('sentiment', [...]);
$assertions = [RegexAssertion::make('/^(positive|negative|neutral)$/')];

$run = $runner->run(SentimentAgent::class, $dataset, $assertions);

expect($run->total_cost_usd)->toBeLessThan(0.05);
expect($run->failures())->toBeEmpty();`

const cliSubjectExample = `use Mosaiqo\\Proofread\\Cli\\Subjects\\ClaudeCodeCliSubject;
use Mosaiqo\\Proofread\\Runner\\EvalRunner;

$subject = ClaudeCodeCliSubject::make()
    ->withModel('claude-sonnet-4-6')
    ->withTimeout(60);

$run = (new EvalRunner())->run($subject, $dataset, $assertions);

// LatencyLimit, TokenBudget, and CostLimit all work with CLI
// subjects unchanged; metadata is parsed from the CLI's JSON output.`
</script>

<template>
  <section id="examples" class="border-t border-border bg-surface-muted py-20 md:py-24">
    <div class="container space-y-10">
      <div class="max-w-2xl space-y-3">
        <p class="font-mono text-sm uppercase tracking-wide text-brand-600 dark:text-brand-400">
          Examples
        </p>
        <h2 class="text-balance text-3xl font-semibold tracking-tight md:text-4xl">
          Four ways to write an eval.
        </h2>
        <p class="text-lg text-muted-foreground">
          Same package, different surface. Pick the one that fits
          how the team already ships code.
        </p>
      </div>

      <Tabs default-value="pest" class="w-full">
        <TabsList class="h-auto flex-wrap gap-1 p-1">
          <TabsTrigger value="pest">Pest eval</TabsTrigger>
          <TabsTrigger value="assertion">Assertion</TabsTrigger>
          <TabsTrigger value="runner">EvalRunner</TabsTrigger>
          <TabsTrigger value="cli">CLI subject</TabsTrigger>
        </TabsList>

        <TabsContent value="pest">
          <CodeBlock
            :code="pestExample"
            lang="php"
            filename="tests/Feature/SentimentEval.php"
          />
        </TabsContent>

        <TabsContent value="assertion">
          <CodeBlock
            :code="assertionExample"
            lang="php"
            filename="tests/Unit/AgentOutputTest.php"
          />
        </TabsContent>

        <TabsContent value="runner">
          <CodeBlock
            :code="runnerExample"
            lang="php"
            filename="app/Jobs/RunSentimentEval.php"
          />
        </TabsContent>

        <TabsContent value="cli">
          <CodeBlock
            :code="cliSubjectExample"
            lang="php"
            filename="app/Evals/ClaudeCodeEval.php"
          />
        </TabsContent>
      </Tabs>
    </div>
  </section>
</template>
