<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Mosaiqo\Proofread\Export\EvalComparisonExporter;
use Mosaiqo\Proofread\Models\EvalComparison;

final class ExportComparisonController
{
    public function __invoke(Request $request, EvalComparison $comparison, EvalComparisonExporter $exporter): Response
    {
        $formatRaw = $request->query('format', 'md');
        $format = is_string($formatRaw) ? strtolower($formatRaw) : 'md';

        if ($format !== 'md' && $format !== 'html') {
            abort(400, sprintf('Unsupported format "%s"; expected "md" or "html".', $format));
        }

        $rendered = $exporter->render($comparison, $format);

        $extension = $format === 'html' ? 'html' : 'md';
        $filename = "eval-comparison-{$comparison->id}.{$extension}";
        $contentType = $format === 'html' ? 'text/html' : 'text/markdown';

        return new Response($rendered, 200, [
            'Content-Type' => $contentType.'; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
