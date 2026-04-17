<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Assertions;

use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use InvalidArgumentException;
use JsonException;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\ObjectSchema;
use Mosaiqo\Proofread\Contracts\Assertion;
use Mosaiqo\Proofread\Support\AssertionResult;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\Validator;
use stdClass;

/**
 * Assertion that validates an agent's output conforms to the JSON schema
 * it declares via {@see HasStructuredOutput}. Produces error messages and
 * metadata tailored to the "LLM must return structured JSON" scenario.
 */
final readonly class StructuredOutputAssertion implements Assertion
{
    private function __construct(
        private string $agentShortName,
        private object $schema,
    ) {}

    public static function conformsTo(string $agentClass): self
    {
        if (! class_exists($agentClass)) {
            throw new InvalidArgumentException(
                sprintf('Agent class [%s] does not exist.', $agentClass)
            );
        }

        if (! is_a($agentClass, HasStructuredOutput::class, true)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Agent class [%s] does not implement [%s].',
                    $agentClass,
                    HasStructuredOutput::class,
                )
            );
        }

        /** @var HasStructuredOutput $agent */
        $agent = app($agentClass);

        $properties = $agent->schema(new JsonSchemaTypeFactory);
        $schemaArray = (new ObjectSchema($properties))->toSchema();

        $schemaObject = self::normalize($schemaArray);

        if (! is_object($schemaObject)) {
            throw new InvalidArgumentException('Agent schema must resolve to an object.');
        }

        $shortName = self::shortName($agentClass);

        return new self($shortName, $schemaObject);
    }

    public function run(mixed $output, array $context = []): AssertionResult
    {
        $data = $this->normalizeOutput($output);

        if ($data instanceof AssertionResult) {
            return $data;
        }

        $validator = new Validator;
        $result = $validator->validate($data, $this->schema);
        $parsedData = $this->toAssocArray($data);

        if ($result->isValid()) {
            return AssertionResult::pass(
                sprintf('Output conforms to structured schema of %s', $this->agentShortName),
                metadata: ['parsed_data' => $parsedData],
            );
        }

        /** @var ValidationError $error */
        $error = $result->error();
        [$reason, $path] = $this->formatError($error);

        return AssertionResult::fail(
            $reason,
            metadata: [
                'parsed_data' => $parsedData,
                'violation_path' => $path,
            ],
        );
    }

    public function name(): string
    {
        return 'structured_output';
    }

    private function normalizeOutput(mixed $output): mixed
    {
        if (is_string($output)) {
            try {
                $decoded = json_decode($output, false, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                return AssertionResult::fail(
                    sprintf('Output is not valid JSON: %s', $e->getMessage())
                );
            }

            return $decoded;
        }

        if (is_array($output)) {
            return self::normalize($output);
        }

        if (is_object($output)) {
            return $output;
        }

        return AssertionResult::fail(
            sprintf(
                'Structured output expected string, array, or object, got %s',
                gettype($output),
            )
        );
    }

    private static function normalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(
                static fn (mixed $item): mixed => self::normalize($item),
                $value,
            );
        }

        $object = new stdClass;
        foreach ($value as $key => $inner) {
            $object->{(string) $key} = self::normalize($inner);
        }

        return $object;
    }

    /**
     * @return array<string, mixed>|list<mixed>|string|int|float|bool|null
     */
    private function toAssocArray(mixed $value): mixed
    {
        if (is_object($value)) {
            $encoded = json_encode($value);
            if ($encoded === false) {
                return [];
            }
            /** @var array<string, mixed>|list<mixed>|null $decoded */
            $decoded = json_decode($encoded, true);

            return $decoded ?? [];
        }

        return $value;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function formatError(ValidationError $error): array
    {
        $leaf = $this->deepestError($error);
        $formatter = new ErrorFormatter;
        $path = '/'.implode('/', array_map(
            static fn (int|string $segment): string => (string) $segment,
            $leaf->data()->fullPath(),
        ));

        $path = $path === '/' ? '' : $path;
        $message = $formatter->formatErrorMessage($leaf);

        $reason = $path === ''
            ? sprintf('Structured output violation: %s', $message)
            : sprintf('Structured output violation at %s: %s', $path, $message);

        return [$reason, $path];
    }

    private function deepestError(ValidationError $error): ValidationError
    {
        $subErrors = $error->subErrors();

        if ($subErrors === []) {
            return $error;
        }

        return $this->deepestError($subErrors[0]);
    }

    private static function shortName(string $class): string
    {
        $pos = strrpos($class, '\\');

        return $pos === false ? $class : substr($class, $pos + 1);
    }
}
