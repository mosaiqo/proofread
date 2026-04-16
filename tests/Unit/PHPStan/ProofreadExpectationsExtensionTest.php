<?php

declare(strict_types=1);

use Mosaiqo\Proofread\PHPStan\ProofreadExpectationsExtension;
use Pest\Expectation;
use Pest\Expectations\OppositeExpectation;
use PHPStan\Reflection\ClassReflection;

/**
 * @param  class-string  $name
 */
function proofread_class_reflection_stub(string $name): ClassReflection
{
    $classReflection = (new ReflectionClass(ClassReflection::class))
        ->newInstanceWithoutConstructor();

    $property = (new ReflectionClass(ClassReflection::class))->getProperty('reflection');
    $property->setAccessible(true);
    $property->setValue($classReflection, new ReflectionClass($name));

    return $classReflection;
}

it('reports hasMethod true for each registered expectation on Pest\\Expectation', function (string $method): void {
    $extension = new ProofreadExpectationsExtension;
    $classReflection = proofread_class_reflection_stub(Expectation::class);

    expect($extension->hasMethod($classReflection, $method))->toBeTrue();
})->with([
    'toPassAssertion',
    'toPassEval',
    'toPassRubric',
    'toMatchSchema',
    'toCostUnder',
    'toMatchGoldenSnapshot',
]);

it('reports hasMethod true for each registered expectation on Pest\\Expectations\\OppositeExpectation', function (string $method): void {
    $extension = new ProofreadExpectationsExtension;
    $classReflection = proofread_class_reflection_stub(OppositeExpectation::class);

    expect($extension->hasMethod($classReflection, $method))->toBeTrue();
})->with([
    'toPassAssertion',
    'toPassEval',
    'toPassRubric',
    'toMatchSchema',
    'toCostUnder',
    'toMatchGoldenSnapshot',
]);

it('reports hasMethod false for unknown methods on Pest\\Expectation', function (): void {
    $extension = new ProofreadExpectationsExtension;
    $classReflection = proofread_class_reflection_stub(Expectation::class);

    expect($extension->hasMethod($classReflection, 'toString'))->toBeFalse();
    expect($extension->hasMethod($classReflection, 'toBeTrue'))->toBeFalse();
});

it('reports hasMethod false for unrelated classes even for known method names', function (): void {
    $extension = new ProofreadExpectationsExtension;
    $classReflection = proofread_class_reflection_stub(DateTime::class);

    expect($extension->hasMethod($classReflection, 'toPassAssertion'))->toBeFalse();
    expect($extension->hasMethod($classReflection, 'toPassEval'))->toBeFalse();
});

it('returns a MethodReflection with the correct name and visibility', function (): void {
    $extension = new ProofreadExpectationsExtension;
    $classReflection = proofread_class_reflection_stub(Expectation::class);

    $method = $extension->getMethod($classReflection, 'toPassAssertion');

    expect($method->getName())->toBe('toPassAssertion');
    expect($method->isPublic())->toBeTrue();
    expect($method->isStatic())->toBeFalse();
    expect($method->isPrivate())->toBeFalse();
});

it('returns a MethodReflection that declares the expectation class as the declaring class', function (): void {
    $extension = new ProofreadExpectationsExtension;
    $classReflection = proofread_class_reflection_stub(Expectation::class);

    $method = $extension->getMethod($classReflection, 'toMatchSchema');

    expect($method->getDeclaringClass())->toBe($classReflection);
});

it('exposes a single variant returning Pest\\Expectation for every registered method', function (string $methodName): void {
    $extension = new ProofreadExpectationsExtension;
    $classReflection = proofread_class_reflection_stub(Expectation::class);

    $method = $extension->getMethod($classReflection, $methodName);
    $variants = $method->getVariants();

    expect($variants)->toHaveCount(1);

    $returnType = $variants[0]->getReturnType();
    expect($returnType->isObject()->yes())->toBeTrue();
    expect($returnType->getObjectClassNames())->toBe(['Pest\\Expectation']);
})->with([
    'toPassAssertion',
    'toPassEval',
    'toPassRubric',
    'toMatchSchema',
    'toCostUnder',
    'toMatchGoldenSnapshot',
]);
