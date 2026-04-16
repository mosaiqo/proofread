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

final readonly class JsonSchemaAssertion implements Assertion
{
    private function __construct(
        private object $schema,
    ) {}

    /**
     * @param  array<string, mixed>  $schema
     */
    public static function fromArray(array $schema): self
    {
        $object = self::normalize($schema);

        if (! is_object($object)) {
            throw new InvalidArgumentException('JSON schema must be an object, not a list');
        }

        return new self($object);
    }

    public static function fromJson(string $json): self
    {
        try {
            $decoded = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidArgumentException(
                sprintf('Invalid JSON schema: %s', $e->getMessage()),
                previous: $e,
            );
        }

        if (! is_object($decoded)) {
            throw new InvalidArgumentException(
                'JSON schema must decode to an object'
            );
        }

        return new self($decoded);
    }

    /**
     * Build an assertion from the structured-output schema declared by an Agent.
     */
    public static function fromAgent(string $agentClass): self
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

        return self::fromArray((new ObjectSchema($properties))->toSchema());
    }

    public static function fromFile(string $path): self
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new InvalidArgumentException(
                sprintf('Schema file not found or unreadable: %s', $path)
            );
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new InvalidArgumentException(
                sprintf('Unable to read schema file: %s', $path)
            );
        }

        return self::fromJson($contents);
    }

    public function run(mixed $output, array $context = []): AssertionResult
    {
        $data = $this->normalizeOutput($output);

        if ($data instanceof AssertionResult) {
            return $data;
        }

        $validator = new Validator;
        $result = $validator->validate($data, $this->schema);

        if ($result->isValid()) {
            return AssertionResult::pass('Output conforms to schema');
        }

        /** @var ValidationError $error */
        $error = $result->error();

        return AssertionResult::fail($this->formatError($error));
    }

    public function name(): string
    {
        return 'json_schema';
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
            sprintf('Expected JSON-decodable string, array, or object output, got %s', gettype($output))
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

    private function formatError(ValidationError $error): string
    {
        $leaf = $this->deepestError($error);
        $formatter = new ErrorFormatter;
        $path = '/'.implode('/', array_map(
            static fn (int|string $segment): string => (string) $segment,
            $leaf->data()->fullPath(),
        ));

        $path = $path === '/' ? '' : $path;
        $message = $formatter->formatErrorMessage($leaf);

        if ($path === '') {
            return sprintf('Schema violation: %s', $message);
        }

        return sprintf('Schema violation at %s: %s', $path, $message);
    }

    private function deepestError(ValidationError $error): ValidationError
    {
        $subErrors = $error->subErrors();

        if ($subErrors === []) {
            return $error;
        }

        return $this->deepestError($subErrors[0]);
    }
}
