<?php declare(strict_types = 1);

namespace Shredio\PhpStanHelpers;

use PHPStan\Analyser\OutOfClassScope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ExtendedMethodReflection;
use PHPStan\Reflection\ExtendedParameterReflection;
use PHPStan\Reflection\ExtendedPropertyReflection;
use PHPStan\Type\Type;
use ReflectionProperty;
use Shredio\PhpStanHelpers\Exception\InvalidTypeException;

/**
 * @api
 */
final readonly class PhpStanReflectionHelper
{

	/**
	 * @return iterable<string, ExtendedParameterReflection>
	 */
	public function getParametersFromMethod(ExtendedMethodReflection $methodReflection): iterable
	{
		foreach ($methodReflection->getVariants() as $variant) {
			foreach ($variant->getParameters() as $parameter) {
				yield $parameter->getName() => $parameter;
			}
		}
	}

	/**
	 * @return array<non-empty-string, Type>
	 *
	 * @throws InvalidTypeException
	 */
	public function getStringKeyWithTypeFromConstantArray(?Type $type): array
	{
		if ($type === null) {
			return [];
		}
		if (!$type->isConstantArray()->yes()) {
			throw new InvalidTypeException();
		}

		if (!$type->isArray()->yes()) {
			return [];
		}

		$values = [];
		foreach ($type->getConstantArrays() as $constantArray) {
			$valueTypes = $constantArray->getValueTypes();
			foreach ($constantArray->getKeyTypes() as $i => $keyType) {
				$key = $keyType->getValue();
				if (!is_string($key) || $key === '') {
					continue;
				}
				if (!isset($valueTypes[$i])) {
					continue;
				}

				$values[$key] = $valueTypes[$i];
			}
		}
		return $values;
	}

	/**
	 * @param array<string, bool>|null $pick
	 * @return iterable<string, ExtendedPropertyReflection>
	 */
	public function getWritablePropertiesFromReflection(
		ClassReflection $reflection,
		?array $pick = null,
		bool $includeStatic = false,
	): iterable
	{
		$scope = new OutOfClassScope();
		foreach ($reflection->getNativeReflection()->getProperties() as $property) {
			if (!$includeStatic && $property->isStatic()) {
				continue;
			}

			$propertyName = $property->getName();
			if ($pick !== null && !isset($pick[$propertyName])) {
				continue;
			}
			if (!$this->isWritableFromOutside($property)) {
				continue;
			}

			yield $propertyName => $reflection->getProperty($propertyName, $scope);
		}
	}

	/**
	 * @return array<string, ExtendedPropertyReflection|ExtendedParameterReflection>
	 */
	public function getWritablePropertiesWithConstructorFromReflection(ClassReflection $reflection): array
	{
		$properties = [];
		foreach ($this->getWritablePropertiesFromReflection($reflection) as $propertyName => $reflectionProperty) {
			$properties[$propertyName] = $reflectionProperty;
		}

		if ($reflection->hasConstructor()) {
			foreach ($this->getParametersFromMethod($reflection->getConstructor()) as $propertyName => $reflectionParameter) {
				$properties[$propertyName] = $reflectionParameter;
			}
		}

		return $properties;
	}

	/**
	 * @param array<string, bool>|null $pick
	 * @return iterable<string, ExtendedPropertyReflection>
	 */
	public function getReadablePropertiesFromReflection(
		ClassReflection $reflection,
		?array $pick = null,
		bool $includeStatic = false,
	): iterable
	{
		$scope = new OutOfClassScope();
		foreach ($reflection->getNativeReflection()->getProperties() as $property) {
			if (!$includeStatic && $property->isStatic()) {
				continue;
			}
			$propertyName = $property->getName();
			if ($pick !== null && !isset($pick[$propertyName])) {
				continue;
			}

			if (!$this->isReadableFromOutside($property)) {
				continue;
			}

			yield $propertyName => $reflection->getProperty($propertyName, $scope);
		}
	}

	public function isReadableFromOutside(ReflectionProperty $property): bool
	{
		if (PHP_VERSION_ID >= 80400) {
			if ($property->hasHooks()) {
				return $property->hasHook(\PropertyHookType::Get);
			}
		}

		return $property->isPublic();
	}

	public function isWritableFromOutside(ReflectionProperty $property): bool
	{
		if ($property->isReadOnly()) {
			return false;
		}
		if (PHP_VERSION_ID >= 80400) {
			if ($property->hasHooks()) {
				return $property->hasHook(\PropertyHookType::Set);
			}
			if ($property->isProtectedSet() || $property->isPrivateSet()) {
				return false;
			}
		}

		return $property->isPublic();
	}

}
