<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\PHPStan;

use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\MethodsClassReflectionExtension;
use PHPStan\Reflection\ParameterReflection;
use PHPStan\Type\ArrayType;
use PHPStan\Type\FloatType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\MixedType;
use PHPStan\Type\NullType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\UnionType;

/**
 * Declares the dynamic Pest expectations registered by Proofread so PHPStan
 * can type-check calls such as expect($value)->toPassAssertion(...) without
 * per-file method.notFound suppressions.
 */
final class ProofreadExpectationsExtension implements MethodsClassReflectionExtension
{
    private const EXPECTATION_CLASSES = [
        'Pest\\Expectation',
        'Pest\\Expectations\\OppositeExpectation',
    ];

    public function hasMethod(ClassReflection $classReflection, string $methodName): bool
    {
        if (! in_array($classReflection->getName(), self::EXPECTATION_CLASSES, true)) {
            return false;
        }

        return array_key_exists($methodName, self::methodDefinitions());
    }

    public function getMethod(ClassReflection $classReflection, string $methodName): MethodReflection
    {
        $definition = self::methodDefinitions()[$methodName];

        return new ProofreadExpectationMethodReflection(
            $classReflection,
            $methodName,
            $definition['parameters'],
            ProofreadExpectationMethodReflection::returnType(),
        );
    }

    /**
     * @return array<string, array{parameters: list<ParameterReflection>}>
     */
    private static function methodDefinitions(): array
    {
        $arrayOrString = new UnionType([
            new ArrayType(new MixedType, new MixedType),
            new StringType,
        ]);

        $numeric = new UnionType([new IntegerType, new FloatType]);

        return [
            'toPassAssertion' => [
                'parameters' => [
                    new ProofreadExpectationParameter(
                        name: 'assertion',
                        type: new ObjectType('Mosaiqo\\Proofread\\Contracts\\Assertion'),
                        optional: false,
                    ),
                ],
            ],
            'toPassEval' => [
                'parameters' => [
                    new ProofreadExpectationParameter(
                        name: 'dataset',
                        type: new ObjectType('Mosaiqo\\Proofread\\Support\\Dataset'),
                        optional: false,
                    ),
                    new ProofreadExpectationParameter(
                        name: 'assertions',
                        type: new ArrayType(new MixedType, new MixedType),
                        optional: true,
                        defaultValue: new ArrayType(new MixedType, new MixedType),
                    ),
                ],
            ],
            'toPassRubric' => [
                'parameters' => [
                    new ProofreadExpectationParameter(
                        name: 'criteria',
                        type: new StringType,
                        optional: false,
                    ),
                    new ProofreadExpectationParameter(
                        name: 'options',
                        type: new ArrayType(new MixedType, new MixedType),
                        optional: true,
                        defaultValue: new ArrayType(new MixedType, new MixedType),
                    ),
                ],
            ],
            'toMatchSchema' => [
                'parameters' => [
                    new ProofreadExpectationParameter(
                        name: 'schema',
                        type: $arrayOrString,
                        optional: false,
                    ),
                ],
            ],
            'toCostUnder' => [
                'parameters' => [
                    new ProofreadExpectationParameter(
                        name: 'maxUsd',
                        type: $numeric,
                        optional: false,
                    ),
                ],
            ],
            'toMatchGoldenSnapshot' => [
                'parameters' => [
                    new ProofreadExpectationParameter(
                        name: 'key',
                        type: TypeCombinator::union(new StringType, new NullType),
                        optional: true,
                        defaultValue: new NullType,
                    ),
                ],
            ],
        ];
    }
}
