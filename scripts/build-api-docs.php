<?php

declare(strict_types=1);

/**
 * Generates the Proofread API reference as markdown files from PHP source
 * via reflection + phpdocumentor DocBlock parsing.
 *
 * Usage:
 *   composer docs:api                       # write markdown files
 *   php scripts/build-api-docs.php --dry-run   # print JSON manifest to stdout
 *
 * Output goes to site/src/content/docs/80-api-reference/.
 */

use phpDocumentor\Reflection\DocBlock;
use phpDocumentor\Reflection\DocBlockFactory;
use phpDocumentor\Reflection\DocBlockFactoryInterface;

require __DIR__.'/../vendor/autoload.php';

const GITHUB_BLOB_BASE = 'https://github.com/mosaiqo/proofread/blob/main/';

$projectRoot = realpath(__DIR__.'/..');
if ($projectRoot === false) {
    fwrite(STDERR, "Cannot resolve project root.\n");
    exit(1);
}

$outputDir = $projectRoot.'/site/src/content/docs/80-api-reference';

$dryRun = in_array('--dry-run', $argv, true);

$categories = [
    'assertions' => [
        'slug' => '01-assertions-api',
        'title' => 'Assertions API',
        'section' => 'API Reference',
        'intro' => 'Concrete assertion classes shipped with Proofread. All implement the `Assertion` interface and return an `AssertionResult` (or `JudgeResult` for LLM-as-judge).',
        'paths' => ['src/Assertions'],
    ],
    'commands' => [
        'slug' => '02-artisan-commands',
        'title' => 'Artisan commands',
        'section' => 'API Reference',
        'intro' => 'Artisan commands exposed by Proofread. Invoke via `php artisan <signature>`.',
        'paths' => ['src/Console/Commands'],
        'emitSignature' => true,
    ],
    'runner' => [
        'slug' => '03-runner',
        'title' => 'Runner & suites',
        'section' => 'API Reference',
        'intro' => 'Eval runners, persisters, subject resolution, and suite base classes.',
        'paths' => ['src/Runner', 'src/Suite'],
    ],
    'models' => [
        'slug' => '04-models',
        'title' => 'Eloquent models',
        'section' => 'API Reference',
        'intro' => 'Persisted eval runs, results, datasets, comparisons, and shadow captures. Only members declared on each class are shown — inherited Eloquent members are omitted.',
        'paths' => ['src/Models'],
    ],
    'support' => [
        'slug' => '05-value-objects',
        'title' => 'Value objects & helpers',
        'section' => 'API Reference',
        'intro' => 'Immutable value objects and helper types used throughout the runner, lint, coverage, CLI subjects, and diff subsystems.',
        'paths' => [
            'src/Support',
            'src/Cli',
            'src/Coverage',
            'src/Simulation',
            'src/Lint',
            'src/Diff',
            'src/Clustering',
            'src/Generator',
        ],
    ],
    'contracts' => [
        'slug' => '06-contracts',
        'title' => 'Contracts & interfaces',
        'section' => 'API Reference',
        'intro' => 'Interfaces and contracts implemented by Proofread internals and by user code that plugs into the eval runtime.',
        'paths' => [
            'src/Contracts',
            'src/Cli/Contracts',
            'src/Lint/Contracts',
            'src/Runner/Concurrency',
            'src/Shadow/Contracts',
        ],
    ],
];

$skipNamespaces = [
    'Mosaiqo\\Proofread\\Http\\Livewire',
    'Mosaiqo\\Proofread\\Stubs',
    'Mosaiqo\\Proofread\\Testing',
    'Mosaiqo\\Proofread\\PHPStan',
];

$skipClassSuffixes = ['ServiceProvider'];

$factory = DocBlockFactory::createInstance();

/**
 * Discover PHP classes/interfaces/traits inside the given paths.
 *
 * @param  array<int, string>  $paths
 * @param  array<int, string>  $skipNamespaces
 * @param  array<int, string>  $skipSuffixes
 * @return list<ReflectionClass<object>>
 */
function discover(string $projectRoot, array $paths, array $skipNamespaces, array $skipSuffixes): array
{
    $out = [];
    foreach ($paths as $relative) {
        $abs = $projectRoot.'/'.$relative;
        if (! is_dir($abs)) {
            continue;
        }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($abs, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }
            $fqcn = classFromFile($file->getPathname());
            if ($fqcn === null) {
                continue;
            }
            foreach ($skipNamespaces as $ns) {
                if (str_starts_with($fqcn, $ns.'\\')) {
                    continue 2;
                }
            }
            foreach ($skipSuffixes as $suffix) {
                if (str_ends_with($fqcn, $suffix)) {
                    continue 2;
                }
            }
            if (! class_exists($fqcn) && ! interface_exists($fqcn) && ! trait_exists($fqcn) && ! enum_exists($fqcn)) {
                continue;
            }
            $reflection = new ReflectionClass($fqcn);
            if ($reflection->isAnonymous()) {
                continue;
            }
            $out[] = $reflection;
        }
    }

    usort($out, fn (ReflectionClass $a, ReflectionClass $b) => strcmp($a->getShortName(), $b->getShortName()));

    return $out;
}

function classFromFile(string $path): ?string
{
    $source = @file_get_contents($path);
    if ($source === false) {
        return null;
    }
    if (! preg_match('/^\s*namespace\s+([^;]+);/m', $source, $nsMatch)) {
        return null;
    }
    if (! preg_match('/^\s*(?:abstract\s+|final\s+|readonly\s+)*(?:class|interface|trait|enum)\s+([A-Za-z_][A-Za-z0-9_]*)/m', $source, $classMatch)) {
        return null;
    }

    return trim($nsMatch[1]).'\\'.$classMatch[1];
}

/**
 * @return array{summary: string, description: string, tags: array<string, list<string>>}
 */
function parseDocBlock(DocBlockFactoryInterface $factory, ?string $raw): array
{
    if ($raw === null || $raw === '' || $raw === false) {
        return ['summary' => '', 'description' => '', 'tags' => []];
    }
    try {
        $doc = $factory->create($raw);
    } catch (Throwable) {
        return ['summary' => '', 'description' => '', 'tags' => []];
    }

    return [
        'summary' => trim($doc->getSummary()),
        'description' => trim((string) $doc->getDescription()),
        'tags' => tagsFor($doc),
    ];
}

/**
 * @return array<string, list<string>>
 */
function tagsFor(DocBlock $doc): array
{
    $out = [];
    foreach ($doc->getTags() as $tag) {
        $out[$tag->getName()][] = trim((string) $tag);
    }

    return $out;
}

function hasInternalTag(array $tags): bool
{
    return isset($tags['internal']);
}

function classKind(ReflectionClass $c): string
{
    if ($c->isInterface()) {
        return 'Interface';
    }
    if ($c->isTrait()) {
        return 'Trait';
    }
    if ($c->isEnum()) {
        return 'Enum';
    }
    if ($c->isAbstract()) {
        return 'Abstract class';
    }
    if ($c->isFinal()) {
        return 'Final class';
    }

    return 'Class';
}

function renderParameter(ReflectionParameter $p): string
{
    $type = $p->getType();
    $typeStr = $type !== null ? renderType($type).' ' : '';
    $prefix = '';
    if ($p->isPassedByReference()) {
        $prefix .= '&';
    }
    if ($p->isVariadic()) {
        $prefix .= '...';
    }
    $default = '';
    if ($p->isDefaultValueAvailable()) {
        try {
            $default = ' = '.renderDefault($p);
        } catch (Throwable) {
            $default = '';
        }
    }

    return rtrim($typeStr).' '.$prefix.'$'.$p->getName().$default;
}

function renderType(ReflectionType $type): string
{
    if ($type instanceof ReflectionUnionType) {
        return implode('|', array_map(fn ($t) => renderType($t), $type->getTypes()));
    }
    if ($type instanceof ReflectionIntersectionType) {
        return implode('&', array_map(fn ($t) => renderType($t), $type->getTypes()));
    }
    if ($type instanceof ReflectionNamedType) {
        $name = $type->getName();
        $short = shortenType($name);
        $nullable = $type->allowsNull() && $name !== 'mixed' && $name !== 'null';

        return ($nullable ? '?' : '').$short;
    }

    return (string) $type;
}

function shortenType(string $name): string
{
    if (in_array($name, ['self', 'static', 'parent', 'mixed', 'void', 'never', 'null', 'true', 'false', 'int', 'float', 'string', 'bool', 'array', 'object', 'iterable', 'callable'], true)) {
        return $name;
    }
    $pos = strrpos($name, '\\');

    return $pos === false ? $name : substr($name, $pos + 1);
}

function renderDefault(ReflectionParameter $p): string
{
    if ($p->isDefaultValueConstant()) {
        $name = $p->getDefaultValueConstantName();

        return $name ?? 'null';
    }
    $value = $p->getDefaultValue();

    return renderValue($value);
}

function renderValue(mixed $value): string
{
    if ($value === null) {
        return 'null';
    }
    if ($value === true) {
        return 'true';
    }
    if ($value === false) {
        return 'false';
    }
    if (is_int($value) || is_float($value)) {
        return var_export($value, true);
    }
    if (is_string($value)) {
        return "'".str_replace("'", "\\'", $value)."'";
    }
    if (is_array($value)) {
        if ($value === []) {
            return '[]';
        }
        $isList = array_is_list($value);
        $parts = [];
        foreach ($value as $k => $v) {
            $parts[] = $isList ? renderValue($v) : renderValue($k).' => '.renderValue($v);
        }

        return '['.implode(', ', $parts).']';
    }

    return '/* '.gettype($value).' */';
}

function renderMethodSignature(ReflectionMethod $m): string
{
    $modifiers = [];
    if ($m->isPublic()) {
        $modifiers[] = 'public';
    } elseif ($m->isProtected()) {
        $modifiers[] = 'protected';
    } elseif ($m->isPrivate()) {
        $modifiers[] = 'private';
    }
    if ($m->isStatic()) {
        $modifiers[] = 'static';
    }
    if ($m->isAbstract() && ! $m->getDeclaringClass()->isInterface()) {
        $modifiers[] = 'abstract';
    }
    if ($m->isFinal()) {
        $modifiers[] = 'final';
    }

    $params = [];
    foreach ($m->getParameters() as $p) {
        $params[] = trim(renderParameter($p));
    }

    $returnType = $m->getReturnType();
    $returnStr = $returnType !== null ? ': '.renderType($returnType) : '';

    return implode(' ', $modifiers).' function '.$m->getName().'('.implode(', ', $params).')'.$returnStr;
}

function escapeMd(string $text): string
{
    return trim($text);
}

/**
 * @param  array<int, ReflectionClass<object>>  $classes
 * @return array<string, string> Short name => "{file}#{anchor}" slug for cross-linking.
 */
function buildClassIndex(array $classes, string $categorySlug): array
{
    $index = [];
    foreach ($classes as $c) {
        $index[$c->getShortName()] = $categorySlug.'#'.strtolower($c->getShortName());
    }

    return $index;
}

function emitCategory(
    DocBlockFactoryInterface $factory,
    string $projectRoot,
    array $cfg,
    array $skipNamespaces,
    array $skipSuffixes,
): array {
    $classes = discover($projectRoot, $cfg['paths'], $skipNamespaces, $skipSuffixes);

    $filtered = [];
    foreach ($classes as $c) {
        $doc = parseDocBlock($factory, $c->getDocComment() === false ? null : $c->getDocComment());
        if (hasInternalTag($doc['tags'])) {
            continue;
        }
        $filtered[] = ['reflection' => $c, 'doc' => $doc];
    }

    $emitSignature = (bool) ($cfg['emitSignature'] ?? false);

    $md = [];
    $md[] = '---';
    $md[] = 'title: '.json_encode($cfg['title']);
    $md[] = 'section: '.json_encode($cfg['section']);
    $md[] = '---';
    $md[] = '';
    $md[] = '# '.$cfg['title'];
    $md[] = '';
    $md[] = '> **[info]** This page is auto-generated from PHP docblocks.';
    $md[] = '> Regenerate with `composer docs:api` after editing any docblock.';
    $md[] = '';
    $md[] = $cfg['intro'];
    $md[] = '';

    if ($filtered === []) {
        $md[] = '_No public classes in this category yet._';
        $md[] = '';

        return ['markdown' => implode("\n", $md), 'count' => 0, 'classes' => []];
    }

    $md[] = '## Summary';
    $md[] = '';
    $md[] = '| Class | Kind | Description |';
    $md[] = '|---|---|---|';
    foreach ($filtered as $entry) {
        /** @var ReflectionClass<object> $c */
        $c = $entry['reflection'];
        $short = $c->getShortName();
        $anchor = strtolower($short);
        $summary = $entry['doc']['summary'] !== '' ? $entry['doc']['summary'] : '—';
        $summary = str_replace(['|', "\n"], [' ', ' '], $summary);
        $md[] = sprintf('| [`%s`](#%s) | %s | %s |', $short, $anchor, classKind($c), $summary);
    }
    $md[] = '';

    foreach ($filtered as $entry) {
        /** @var ReflectionClass<object> $c */
        $c = $entry['reflection'];
        $doc = $entry['doc'];

        $md[] = '---';
        $md[] = '';
        $md[] = '## `'.$c->getShortName().'`';
        $md[] = '';

        $sourceRel = relativeSourcePath($projectRoot, $c);
        $md[] = '- **Kind:** '.classKind($c);
        $md[] = '- **Namespace:** `'.$c->getNamespaceName().'`';
        if ($sourceRel !== null) {
            $md[] = '- **Source:** ['.$sourceRel.']('.GITHUB_BLOB_BASE.$sourceRel.')';
        }
        if ($c->getParentClass() !== false) {
            $md[] = '- **Extends:** `'.$c->getParentClass()->getName().'`';
        }
        $interfaces = $c->getInterfaceNames();
        if ($interfaces !== [] && ! $c->isInterface()) {
            $md[] = '- **Implements:** '.implode(', ', array_map(fn ($i) => '`'.$i.'`', $interfaces));
        }
        if (isset($doc['tags']['deprecated'])) {
            $md[] = '';
            $md[] = '> **[warn]** Deprecated: '.implode('; ', $doc['tags']['deprecated']);
        }
        $md[] = '';

        if ($doc['summary'] !== '') {
            $md[] = $doc['summary'];
            $md[] = '';
        }
        if ($doc['description'] !== '') {
            $md[] = $doc['description'];
            $md[] = '';
        }

        if ($emitSignature) {
            [$signature, $descriptionText] = extractCommandMeta($c);
            if ($signature !== null) {
                $md[] = '### Artisan signature';
                $md[] = '';
                $md[] = '```bash';
                $md[] = 'php artisan '.$signature;
                $md[] = '```';
                $md[] = '';
                if ($descriptionText !== null && $descriptionText !== '') {
                    $md[] = '> '.$descriptionText;
                    $md[] = '';
                }
            }
        }

        $constructors = [];
        $instanceMethods = [];
        foreach ($c->getMethods(ReflectionMethod::IS_PUBLIC) as $m) {
            if ($m->getDeclaringClass()->getName() !== $c->getName()) {
                continue;
            }
            $mDoc = parseDocBlock($factory, $m->getDocComment() === false ? null : $m->getDocComment());
            if (hasInternalTag($mDoc['tags'])) {
                continue;
            }
            if ($m->getName() === '__construct') {
                if (! $m->isPublic()) {
                    continue;
                }
                $instanceMethods[] = ['m' => $m, 'doc' => $mDoc];

                continue;
            }
            if (str_starts_with($m->getName(), '__')) {
                continue;
            }
            if ($m->isStatic()) {
                $constructors[] = ['m' => $m, 'doc' => $mDoc];
            } else {
                $instanceMethods[] = ['m' => $m, 'doc' => $mDoc];
            }
        }

        if ($constructors !== []) {
            $md[] = '### Named constructors & static methods';
            $md[] = '';
            foreach ($constructors as $entry) {
                renderMethod($md, $entry['m'], $entry['doc']);
            }
        }

        if ($instanceMethods !== []) {
            $md[] = '### Methods';
            $md[] = '';
            foreach ($instanceMethods as $entry) {
                renderMethod($md, $entry['m'], $entry['doc']);
            }
        }

        $props = [];
        foreach ($c->getProperties(ReflectionProperty::IS_PUBLIC) as $p) {
            if ($p->getDeclaringClass()->getName() !== $c->getName()) {
                continue;
            }
            $pDoc = parseDocBlock($factory, $p->getDocComment() === false ? null : $p->getDocComment());
            if (hasInternalTag($pDoc['tags'])) {
                continue;
            }
            $props[] = ['p' => $p, 'doc' => $pDoc];
        }

        if ($props !== []) {
            $md[] = '### Public properties';
            $md[] = '';
            foreach ($props as $entry) {
                /** @var ReflectionProperty $p */
                $p = $entry['p'];
                $type = $p->getType();
                $typeStr = $type !== null ? renderType($type).' ' : '';
                $readonly = $p->isReadOnly() ? 'readonly ' : '';
                $md[] = '- '.$readonly.'`'.$typeStr.'$'.$p->getName().'`'.($entry['doc']['summary'] !== '' ? ' — '.$entry['doc']['summary'] : '');
            }
            $md[] = '';
        }

        if ($c->isEnum()) {
            $enumRef = new ReflectionEnum($c->getName());
            if ($enumRef->getCases() !== []) {
                $md[] = '### Cases';
                $md[] = '';
                foreach ($enumRef->getCases() as $case) {
                    $md[] = '- `'.$case->getName().'`';
                }
                $md[] = '';
            }
        }
    }

    return [
        'markdown' => implode("\n", $md),
        'count' => count($filtered),
        'classes' => array_map(fn ($e) => $e['reflection']->getName(), $filtered),
    ];
}

/**
 * @param  list<string>  $md
 * @param  array{summary:string, description:string, tags: array<string, list<string>>}  $doc
 */
function renderMethod(array &$md, ReflectionMethod $m, array $doc): void
{
    $md[] = '#### `'.$m->getName().'()`';
    $md[] = '';
    $md[] = '```php';
    $md[] = renderMethodSignature($m);
    $md[] = '```';
    $md[] = '';
    if ($doc['summary'] !== '') {
        $md[] = $doc['summary'];
        $md[] = '';
    }
    if ($doc['description'] !== '') {
        $md[] = $doc['description'];
        $md[] = '';
    }
    if (isset($doc['tags']['throws'])) {
        $md[] = '**Throws:** '.implode(', ', array_map(fn ($t) => '`'.trim((string) $t).'`', $doc['tags']['throws']));
        $md[] = '';
    }
    if (isset($doc['tags']['deprecated'])) {
        $md[] = '> **[warn]** Deprecated: '.implode('; ', $doc['tags']['deprecated']);
        $md[] = '';
    }
}

/**
 * @return array{0: ?string, 1: ?string}
 */
function extractCommandMeta(ReflectionClass $c): array
{
    if (! $c->isInstantiable()) {
        return [null, null];
    }
    try {
        $defaults = $c->getDefaultProperties();
    } catch (Throwable) {
        return [null, null];
    }
    $signature = $defaults['signature'] ?? null;
    $description = $defaults['description'] ?? null;
    if (is_string($signature)) {
        $signature = preg_replace('/\s+/', ' ', trim($signature));
    } else {
        $signature = null;
    }
    if (! is_string($description) || $description === '') {
        $description = null;
    }

    return [$signature, $description];
}

function relativeSourcePath(string $projectRoot, ReflectionClass $c): ?string
{
    $file = $c->getFileName();
    if ($file === false) {
        return null;
    }
    $real = realpath($file);
    if ($real === false) {
        return null;
    }
    $prefix = $projectRoot.DIRECTORY_SEPARATOR;
    if (! str_starts_with($real, $prefix)) {
        return null;
    }

    return str_replace(DIRECTORY_SEPARATOR, '/', substr($real, strlen($prefix)));
}

$manifest = [];
$totalClasses = 0;
$totalWords = 0;

foreach ($categories as $key => $cfg) {
    $result = emitCategory($factory, $projectRoot, $cfg, $skipNamespaces, $skipClassSuffixes);
    $filePath = $outputDir.'/'.$cfg['slug'].'.md';
    $content = rtrim($result['markdown'])."\n";

    if ($dryRun) {
        $manifest[$key] = [
            'title' => $cfg['title'],
            'path' => str_replace($projectRoot.'/', '', $filePath),
            'classCount' => $result['count'],
            'classes' => $result['classes'],
            'wordCount' => str_word_count(strip_tags($content)),
        ];
    } else {
        if (! is_dir(dirname($filePath))) {
            mkdir(dirname($filePath), 0755, true);
        }
        file_put_contents($filePath, $content);
        $manifest[$key] = [
            'title' => $cfg['title'],
            'path' => str_replace($projectRoot.'/', '', $filePath),
            'classCount' => $result['count'],
            'wordCount' => str_word_count(strip_tags($content)),
        ];
        fwrite(STDOUT, sprintf("Wrote %s (%d classes, %d words)\n", $manifest[$key]['path'], $result['count'], $manifest[$key]['wordCount']));
    }

    $totalClasses += $result['count'];
    $totalWords += $manifest[$key]['wordCount'];
}

if ($dryRun) {
    fwrite(STDOUT, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
} else {
    fwrite(STDOUT, sprintf("Total: %d classes across %d categories, %d words.\n", $totalClasses, count($categories), $totalWords));
}
