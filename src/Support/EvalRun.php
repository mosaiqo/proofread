<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Support;

use DOMDocument;
use DOMElement;
use InvalidArgumentException;
use Throwable;

final readonly class EvalRun
{
    /**
     * @param  list<EvalResult>  $results
     */
    private function __construct(
        public Dataset $dataset,
        public array $results,
        public float $durationMs,
    ) {}

    /**
     * @param  array<int, mixed>  $results
     */
    public static function make(Dataset $dataset, array $results, float $durationMs): self
    {
        if ($durationMs < 0.0) {
            throw new InvalidArgumentException(
                sprintf('Duration must be >= 0, got %F.', $durationMs)
            );
        }

        $normalized = [];
        foreach ($results as $index => $result) {
            if (! $result instanceof EvalResult) {
                throw new InvalidArgumentException(
                    sprintf(
                        'results[%d] must be an EvalResult, got %s.',
                        $index,
                        get_debug_type($result),
                    )
                );
            }
            $normalized[] = $result;
        }

        if (count($normalized) > $dataset->count()) {
            throw new InvalidArgumentException(
                sprintf(
                    'Cannot have more results (%d) than dataset cases (%d).',
                    count($normalized),
                    $dataset->count(),
                )
            );
        }

        return new self($dataset, $normalized, $durationMs);
    }

    public function passed(): bool
    {
        foreach ($this->results as $result) {
            if (! $result->passed()) {
                return false;
            }
        }

        return true;
    }

    public function failed(): bool
    {
        return ! $this->passed();
    }

    public function passedCount(): int
    {
        $count = 0;
        foreach ($this->results as $result) {
            if ($result->passed()) {
                $count++;
            }
        }

        return $count;
    }

    public function failedCount(): int
    {
        return $this->total() - $this->passedCount();
    }

    public function total(): int
    {
        return count($this->results);
    }

    public function passRate(): float
    {
        $total = $this->total();
        if ($total === 0) {
            return 1.0;
        }

        return $this->passedCount() / $total;
    }

    /**
     * @return list<EvalResult>
     */
    public function failures(): array
    {
        $failures = [];
        foreach ($this->results as $result) {
            if ($result->failed()) {
                $failures[] = $result;
            }
        }

        return $failures;
    }

    public function toJUnitXml(): string
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        $errorsCount = $this->countErrors();
        $failuresCount = $this->countFailures();
        $time = $this->formatSeconds($this->durationMs);

        $testsuites = $doc->createElement('testsuites');
        $testsuites->setAttribute('name', 'proofread');
        $testsuites->setAttribute('tests', (string) $this->total());
        $testsuites->setAttribute('failures', (string) $failuresCount);
        $testsuites->setAttribute('errors', (string) $errorsCount);
        $testsuites->setAttribute('time', $time);
        $doc->appendChild($testsuites);

        $testsuite = $doc->createElement('testsuite');
        $testsuite->setAttribute('name', $this->dataset->name);
        $testsuite->setAttribute('tests', (string) $this->total());
        $testsuite->setAttribute('failures', (string) $failuresCount);
        $testsuite->setAttribute('errors', (string) $errorsCount);
        $testsuite->setAttribute('time', $time);
        $testsuite->setAttribute('timestamp', gmdate('c'));
        $testsuites->appendChild($testsuite);

        $testsuite->appendChild($this->buildProperties($doc));

        $classname = 'proofread.'.$this->sanitizeDatasetName($this->dataset->name);

        foreach ($this->results as $index => $result) {
            $testsuite->appendChild($this->buildTestcase($doc, $result, $index, $classname));
        }

        $xml = $doc->saveXML();

        return $xml === false ? '' : $xml;
    }

    private function countFailures(): int
    {
        $count = 0;
        foreach ($this->results as $result) {
            if ($result->error === null && $result->failed()) {
                $count++;
            }
        }

        return $count;
    }

    private function countErrors(): int
    {
        $count = 0;
        foreach ($this->results as $result) {
            if ($result->error !== null) {
                $count++;
            }
        }

        return $count;
    }

    private function formatSeconds(float $ms): string
    {
        return number_format($ms / 1000.0, 3, '.', '');
    }

    private function sanitizeDatasetName(string $name): string
    {
        $sanitized = preg_replace('/\s+/', '_', $name);

        return $sanitized ?? $name;
    }

    private function buildProperties(DOMDocument $doc): DOMElement
    {
        $properties = $doc->createElement('properties');

        $datasetProp = $doc->createElement('property');
        $datasetProp->setAttribute('name', 'proofread.dataset');
        $datasetProp->setAttribute('value', $this->dataset->name);
        $properties->appendChild($datasetProp);

        $costs = $this->collectMetadataNumbers('cost_usd');
        if ($costs !== []) {
            $total = array_sum($costs);
            $prop = $doc->createElement('property');
            $prop->setAttribute('name', 'proofread.total_cost_usd');
            $prop->setAttribute('value', (string) round($total, 6));
            $properties->appendChild($prop);
        }

        $tokensIn = $this->collectMetadataNumbers('tokens_in');
        if ($tokensIn !== []) {
            $total = array_sum($tokensIn);
            $prop = $doc->createElement('property');
            $prop->setAttribute('name', 'proofread.total_tokens_in');
            $prop->setAttribute('value', (string) $total);
            $properties->appendChild($prop);
        }

        $tokensOut = $this->collectMetadataNumbers('tokens_out');
        if ($tokensOut !== []) {
            $total = array_sum($tokensOut);
            $prop = $doc->createElement('property');
            $prop->setAttribute('name', 'proofread.total_tokens_out');
            $prop->setAttribute('value', (string) $total);
            $properties->appendChild($prop);
        }

        return $properties;
    }

    /**
     * @return list<float|int>
     */
    private function collectMetadataNumbers(string $key): array
    {
        $values = [];
        foreach ($this->results as $result) {
            foreach ($result->assertionResults as $assertion) {
                if (! array_key_exists($key, $assertion->metadata)) {
                    continue;
                }
                $value = $assertion->metadata[$key];
                if (is_int($value) || is_float($value)) {
                    $values[] = $value;
                }
            }
        }

        return $values;
    }

    private function buildTestcase(DOMDocument $doc, EvalResult $result, int $index, string $classname): DOMElement
    {
        $testcase = $doc->createElement('testcase');
        $testcase->setAttribute('name', $this->testcaseName($result, $index));
        $testcase->setAttribute('classname', $classname);
        $testcase->setAttribute('time', $this->formatSeconds($result->durationMs));

        if ($result->error !== null) {
            $testcase->appendChild($this->buildError($doc, $result->error));

            return $testcase;
        }

        if ($result->failed()) {
            $testcase->appendChild($this->buildFailure($doc, $result, $index));
        }

        return $testcase;
    }

    private function testcaseName(EvalResult $result, int $index): string
    {
        $meta = $result->case['meta'] ?? null;
        if (is_array($meta)) {
            $name = $meta['name'] ?? null;
            if (is_string($name) && $name !== '') {
                return $name;
            }
        }

        return 'case_'.$index;
    }

    private function buildFailure(DOMDocument $doc, EvalResult $result, int $index): DOMElement
    {
        $failedAssertions = [];
        foreach ($result->assertionResults as $assertion) {
            if (! $assertion->passed) {
                $failedAssertions[] = $assertion;
            }
        }

        $first = $failedAssertions[0] ?? null;
        $firstName = $first !== null ? $this->assertionName($first) : 'unknown';
        $firstReason = $first !== null ? $first->reason : '';
        $remaining = max(0, count($failedAssertions) - 1);
        $message = $firstName.': '.$firstReason;
        if ($remaining > 0) {
            $message .= ' (+'.$remaining.' more)';
        }

        $testcaseName = $this->testcaseName($result, $index);
        $lines = [
            $testcaseName.' failed '.count($failedAssertions).' '.(count($failedAssertions) === 1 ? 'assertion:' : 'assertions:'),
        ];
        foreach ($failedAssertions as $assertion) {
            $lines[] = '  '.$this->assertionName($assertion).': '.$assertion->reason;
        }
        $lines[] = '  input: '.$this->stringify($this->truncateValue($result->case['input'] ?? null, 200));
        $lines[] = '  output: '.$this->stringify($this->truncateValue($result->output, 200));

        $failure = $doc->createElement('failure');
        $failure->setAttribute('message', $message);
        $failure->setAttribute('type', 'AssertionFailure');
        $failure->appendChild($doc->createTextNode(implode("\n", $lines)));

        return $failure;
    }

    private function buildError(DOMDocument $doc, Throwable $error): DOMElement
    {
        $firstLine = strtok($error->getMessage(), "\n");
        if ($firstLine === false) {
            $firstLine = '';
        }
        $message = $this->truncate($firstLine, 200);

        $element = $doc->createElement('error');
        $element->setAttribute('message', $message);
        $element->setAttribute('type', $error::class);
        $body = $error::class.': '.$error->getMessage()."\n".$error->getTraceAsString();
        $element->appendChild($doc->createTextNode($body));

        return $element;
    }

    private function assertionName(AssertionResult $assertion): string
    {
        $name = $assertion->metadata['assertion_name'] ?? null;
        if (is_string($name) && $name !== '') {
            return $name;
        }

        return 'unknown';
    }

    private function stringify(mixed $value): string
    {
        if (is_string($value)) {
            return '"'.$value.'"';
        }
        if (is_int($value) || is_float($value) || is_bool($value) || $value === null) {
            return var_export($value, true);
        }
        $encoded = json_encode($value);

        return $encoded === false ? get_debug_type($value) : $encoded;
    }

    private function truncate(string $value, int $max): string
    {
        if (mb_strlen($value) <= $max) {
            return $value;
        }

        return mb_substr($value, 0, $max);
    }

    private function truncateValue(mixed $value, int $max): mixed
    {
        if (is_string($value)) {
            return $this->truncate($value, $max);
        }

        return $value;
    }
}
