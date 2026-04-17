<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Coverage;

use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;
use Mosaiqo\Proofread\Clustering\FailureClusterer;
use Mosaiqo\Proofread\Models\EvalDataset;
use Mosaiqo\Proofread\Models\EvalDatasetVersion;
use Mosaiqo\Proofread\Models\ShadowCapture;
use Mosaiqo\Proofread\Similarity\Similarity;

/**
 * Measure how well a dataset reflects production traffic by comparing the
 * embeddings of the dataset's case inputs against the embeddings of the
 * agent's recent shadow captures.
 *
 * Each capture is attached to its nearest case by cosine similarity. If the
 * max similarity meets the threshold, the capture is "covered"; otherwise
 * it is an uncovered data point that suggests a gap the dataset should
 * grow to include. Uncovered captures are clustered so operators see the
 * patterns behind the gap instead of a flat list of stragglers.
 *
 * Cost caveat: embedding N cases plus M captures costs tokens. With
 * OpenAI text-embedding-3-small at roughly $0.02 per 1M tokens this is
 * cheap for typical sizes, but not free. The maxCaptures cap prevents
 * accidentally running this against tens of thousands of captures.
 */
final class CoverageAnalyzer
{
    public function __construct(
        private readonly Similarity $similarity,
        private readonly FailureClusterer $clusterer,
    ) {}

    public function analyze(
        string $agentClass,
        string $datasetName,
        DateTimeInterface $from,
        DateTimeInterface $to,
        float $threshold = 0.7,
        int $maxCaptures = 500,
        ?string $model = null,
    ): CoverageReport {
        if ($threshold < -1.0 || $threshold > 1.0) {
            throw new InvalidArgumentException(sprintf(
                'Threshold must be between -1.0 and 1.0, got %F.',
                $threshold,
            ));
        }

        if ($maxCaptures < 1) {
            throw new InvalidArgumentException(sprintf(
                'maxCaptures must be >= 1, got %d.',
                $maxCaptures,
            ));
        }

        $fromImmutable = DateTimeImmutable::createFromInterface($from);
        $toImmutable = DateTimeImmutable::createFromInterface($to);

        $dataset = EvalDataset::query()->where('name', $datasetName)->first();
        if (! $dataset instanceof EvalDataset) {
            throw new InvalidArgumentException(sprintf(
                'Dataset "%s" does not exist.',
                $datasetName,
            ));
        }

        /** @var EvalDatasetVersion|null $version */
        $version = EvalDatasetVersion::query()
            ->where('eval_dataset_id', $dataset->id)
            ->orderBy('first_seen_at', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        /** @var list<array<string, mixed>> $cases */
        $cases = $version instanceof EvalDatasetVersion ? $version->cases : [];

        /** @var Collection<int, ShadowCapture> $captures */
        $captures = ShadowCapture::query()
            ->where('agent_class', $agentClass)
            ->where('captured_at', '>=', $fromImmutable)
            ->where('captured_at', '<=', $toImmutable)
            ->orderBy('captured_at', 'desc')
            ->limit($maxCaptures)
            ->get();

        $totalCaptures = $captures->count();

        if ($totalCaptures === 0) {
            return new CoverageReport(
                agentClass: $agentClass,
                datasetName: $datasetName,
                totalCaptures: 0,
                coveredCount: 0,
                uncoveredCount: 0,
                skippedCount: 0,
                threshold: $threshold,
                caseCoverage: [],
                uncovered: [],
                uncoveredClusters: [],
                from: $fromImmutable,
                to: $toImmutable,
            );
        }

        /** @var list<array{index: int, name: ?string, input: string}> $caseEntries */
        $caseEntries = [];
        foreach ($cases as $index => $case) {
            if (! array_key_exists('input', $case)) {
                continue;
            }
            $stringified = $this->stringifyInput($case['input']);
            if ($stringified === '') {
                continue;
            }
            $caseEntries[] = [
                'index' => $index,
                'name' => $this->caseName($case),
                'input' => $stringified,
            ];
        }

        /** @var list<array{capture: ShadowCapture, input: string}> $captureEntries */
        $captureEntries = [];
        $skippedCount = 0;
        foreach ($captures as $capture) {
            $stringified = $this->stringifyInput($capture->input_payload);
            if ($stringified === '') {
                $skippedCount++;

                continue;
            }
            $captureEntries[] = [
                'capture' => $capture,
                'input' => $stringified,
            ];
        }

        if ($caseEntries === [] || $captureEntries === []) {
            return new CoverageReport(
                agentClass: $agentClass,
                datasetName: $datasetName,
                totalCaptures: $totalCaptures,
                coveredCount: 0,
                uncoveredCount: 0,
                skippedCount: $skippedCount + count($captureEntries),
                threshold: $threshold,
                caseCoverage: [],
                uncovered: [],
                uncoveredClusters: [],
                from: $fromImmutable,
                to: $toImmutable,
            );
        }

        $allTexts = [];
        foreach ($caseEntries as $entry) {
            $allTexts[] = $entry['input'];
        }
        foreach ($captureEntries as $entry) {
            $allTexts[] = $entry['input'];
        }

        $embeddings = $this->similarity->embed($allTexts, $model);

        $caseCount = count($caseEntries);
        $caseVectors = array_slice($embeddings, 0, $caseCount);
        $captureVectors = array_slice($embeddings, $caseCount);

        /** @var array<int, int> $matchedByCase */
        $matchedByCase = [];
        /** @var array<int, float> $sumByCase */
        $sumByCase = [];
        foreach ($caseEntries as $entry) {
            $matchedByCase[$entry['index']] = 0;
            $sumByCase[$entry['index']] = 0.0;
        }

        $uncovered = [];
        $coveredCount = 0;

        foreach ($captureEntries as $offset => $captureEntry) {
            $captureVector = $captureVectors[$offset];
            $bestScore = -INF;
            $bestEntryOffset = 0;

            foreach ($caseVectors as $vectorOffset => $caseVector) {
                $score = Similarity::cosineFromVectors($caseVector, $captureVector);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestEntryOffset = $vectorOffset;
                }
            }

            $bestEntry = $caseEntries[$bestEntryOffset];

            if ($bestScore >= $threshold) {
                $matchedByCase[$bestEntry['index']]++;
                $sumByCase[$bestEntry['index']] += (float) $bestScore;
                $coveredCount++;
            } else {
                $uncovered[] = new UncoveredCapture(
                    captureId: (string) $captureEntry['capture']->id,
                    inputSnippet: $this->snippet($captureEntry['input']),
                    maxSimilarity: (float) $bestScore,
                    nearestCaseIndex: $bestEntry['index'],
                );
            }
        }

        $caseCoverage = [];
        foreach ($caseEntries as $entry) {
            $matched = $matchedByCase[$entry['index']];
            $sum = $sumByCase[$entry['index']];
            $avg = $matched > 0 ? $sum / $matched : 0.0;
            $caseCoverage[] = new CaseCoverage(
                caseIndex: $entry['index'],
                caseName: $entry['name'],
                matchedCaptures: $matched,
                avgSimilarity: $avg,
            );
        }

        $uncoveredClusters = [];
        if ($uncovered !== []) {
            $signals = array_map(
                static fn (UncoveredCapture $u): string => $u->inputSnippet,
                $uncovered,
            );
            $uncoveredClusters = $this->clusterer->cluster($signals, $threshold, $model);
        }

        return new CoverageReport(
            agentClass: $agentClass,
            datasetName: $datasetName,
            totalCaptures: $totalCaptures,
            coveredCount: $coveredCount,
            uncoveredCount: count($uncovered),
            skippedCount: $skippedCount,
            threshold: $threshold,
            caseCoverage: $caseCoverage,
            uncovered: $uncovered,
            uncoveredClusters: $uncoveredClusters,
            from: $fromImmutable,
            to: $toImmutable,
        );
    }

    private function stringifyInput(mixed $input): string
    {
        if (is_string($input)) {
            return $input;
        }

        if (is_array($input)) {
            if ($input === []) {
                return '';
            }
            $encoded = json_encode(
                $input,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );

            return $encoded === false ? '' : $encoded;
        }

        if (is_scalar($input)) {
            return (string) $input;
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $case
     */
    private function caseName(array $case): ?string
    {
        if (array_key_exists('meta', $case) && is_array($case['meta'])) {
            $name = $case['meta']['name'] ?? null;
            if (is_string($name) && $name !== '') {
                return $name;
            }
        }

        $name = $case['name'] ?? null;
        if (is_string($name) && $name !== '') {
            return $name;
        }

        return null;
    }

    private function snippet(string $input): string
    {
        if (strlen($input) <= 200) {
            return $input;
        }

        return substr($input, 0, 200);
    }
}
