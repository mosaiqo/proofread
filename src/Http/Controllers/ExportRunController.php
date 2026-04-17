<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Mosaiqo\Proofread\Export\EvalRunExporter;
use Mosaiqo\Proofread\Models\EvalRun;

final class ExportRunController
{
    public function __invoke(Request $request, EvalRun $run, EvalRunExporter $exporter): Response
    {
        $formatRaw = $request->query('format', 'md');
        $format = is_string($formatRaw) ? strtolower($formatRaw) : 'md';

        if ($format !== 'md' && $format !== 'html') {
            abort(400, sprintf('Unsupported format "%s"; expected "md" or "html".', $format));
        }

        $rendered = $exporter->render($run, $format);

        $extension = $format === 'html' ? 'html' : 'md';
        $filename = "eval-run-{$run->id}.{$extension}";
        $contentType = $format === 'html' ? 'text/html' : 'text/markdown';

        return new Response($rendered, 200, [
            'Content-Type' => $contentType.'; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
