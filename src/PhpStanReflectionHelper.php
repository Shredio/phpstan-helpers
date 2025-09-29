<?php declare(strict_types = 1);

namespace Shredio\PhpStanHelpers;

use PHPStan\Analyser\OutOfClassScope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ExtendedMethodReflection;
use PHPStan\Reflection\ExtendedParameterReflection;
use PHPStan\Reflection\ExtendedPropertyReflection;
use PHPStan\Type\Type;
use ReflectionProperty;
use Shredio\PhpStanHelpers\Exception\EmptyTypeException;
use Shredio\PhpStanHelpers\Exception\InvalidTypeException;
use Shredio\PhpStanHelpers\Exception\NonConstantTypeException;
use Shredio\PhpStanHelpers\Helper\PropertyPicker;

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
	 * Returns a list of non-empty constant strings.
	 *
	 * @return non-empty-list<non-empty-string>
	 *
	 * @throws InvalidTypeException if the type is not a string
	 * @throws NonConstantTypeException if the type is string but not constant
	 * @throws EmptyTypeException if the type is constant string but contains only empty strings
	 */
	public function getNonEmptyStringsFromStringType(Type $type): array
	{
		if (!$type->isString()->yes()) {
			throw new InvalidTypeException();
		}

		$constantStrings = $type->getConstantStrings();
		if ($constantStrings === []) {
			throw new NonConstantTypeException();
		}

		$values = [];
		foreach ($constantStrings as $constantString) {
			$value = $constantString->getValue();
			if ($value === '') {
				continue;
			}

			$values[] = $value;
		}
		if ($values === []) {
			throw new EmptyTypeException();
		}

		return $values;
	}

	/**
	 * Returns a list of non-empty constant strings from a constant array or list type.
	 *
	 * @return non-empty-list<non-empty-string>
	 *
	 * @throws InvalidTypeException if the type is not an array or list
	 * @throws NonConstantTypeException if the type is not a constant array
	 * @throws EmptyTypeException if the type is constant array but contains no non-empty strings
	 */
	public function getNonEmptyStringsFromConstantArrayType(Type $type): array
	{
		if (!$type->isArray()->yes()) {
			throw new InvalidTypeException();
		}
		if (!$type->isConstantValue()->yes()) {
			throw new NonConstantTypeException();
		}

		$values = [];
		foreach ($type->getConstantArrays() as $constantArray) {
			foreach ($constantArray->getValueTypes() as $valueType) {
				foreach ($valueType->getConstantStrings() as $constantString) {
					$value = $constantString->getValue();
					if ($value === '') {
						continue 2;
					}
					$values[] = $value;
				}
			}
		}
		if ($values === []) {
			throw new EmptyTypeException();
		}

		return $values;
	}

	/**
	 * Returns `true` or `false`.
	 *
	 * @throws InvalidTypeException if the type is not a boolean
	 * @throws NonConstantTypeException if the type is boolean but not constant
	 */
	public function getTrueOrFalseFromConstantBoolean(Type $type): bool
	{
		if (!$type->isBoolean()->yes()) {
			throw new InvalidTypeException();
		}

		if ($type->isTrue()->yes()) {
			return true;
		}
		if ($type->isFalse()->yes()) {
			return false;
		}

		throw new NonConstantTypeException();
	}

	/**
	 * @throws InvalidTypeException if the type is not a class-string or is union/intersection
	 * @throws EmptyTypeException if the type is class-string but empty (i.e. `class-string`)
	 */
	public function getClassReflectionFromClassString(Type $type): ClassReflection
	{
		if (!$type->isClassString()->yes()) {
			throw new InvalidTypeException();
		}

		$objectType = $type->getClassStringObjectType();
		$classReflections = $objectType->getObjectClassReflections();
		$count = count($classReflections);
		if ($count > 1) {
			throw new InvalidTypeException();
		}
		if ($count === 0) {
			throw new EmptyTypeException();
		}

		return $classReflections[0];
	}

	/**
	 * Returns a map of non-empty constant string keys to their types.
	 *
	 * @return array<non-empty-string, Type>
	 *
	 * @throws InvalidTypeException if the type is not an array
	 * @throws NonConstantTypeException if the type is not a constant array
	 */
	public function getNonEmptyStringKeyWithTypeFromConstantArray(Type $type): array
	{
		if (!$type->isArray()->yes()) {
			throw new InvalidTypeException();
		}
		if (!$type->isConstantArray()->yes()) {
			throw new NonConstantTypeException();
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
	 * Yields value types from a constant list.
	 *
	 * @return list<Type>
	 *
	 * @throws InvalidTypeException if the type is not a list
	 * @throws NonConstantTypeException if the type is a list but not constant
	 */
	public function getValueTypesFromConstantList(Type $type): array
	{
		if (!$type->isList()->yes()) {
			throw new InvalidTypeException();
		}
		if (!$type->isConstantArray()->yes()) {
			throw new NonConstantTypeException();
		}

		$values = [];
		foreach ($type->getConstantArrays() as $constantArray) {
			foreach ($constantArray->getValueTypes() as $valueType) {
				$values[] = $valueType;
			}
		}
		return $values;
	}

	/**
	 * @return array<string, ExtendedPropertyReflection>
	 */
	public function getWritablePropertiesFromReflection(
		ClassReflection $reflection,
		?PropertyPicker $picker = null,
		bool $includeStatic = false,
	): array
	{
		$scope = new OutOfClassScope();
		$properties = [];
		foreach ($reflection->getNativeReflection()->getProperties() as $property) {
			if (!$includeStatic && $property->isStatic()) {
				continue;
			}

			$propertyName = $property->getName();
			if ($picker?->shouldPick($propertyName) === false) {
				continue;
			}
			if (!$this->isWritableFromOutside($property)) {
				continue;
			}

			$properties[$propertyName] = $reflection->getProperty($propertyName, $scope);
		}
		return $properties;
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
	 * @return array<string, ExtendedPropertyReflection>
	 */
	public function getReadablePropertiesFromReflection(
		ClassReflection $reflection,
		?PropertyPicker $picker = null,
		bool $includeStatic = false,
	): array
	{
		$scope = new OutOfClassScope();
		$properties = [];
		foreach ($reflection->getNativeReflection()->getProperties() as $property) {
			if (!$includeStatic && $property->isStatic()) {
				continue;
			}
			$propertyName = $property->getName();
			if ($picker?->shouldPick($propertyName) === false) {
				continue;
			}
			if (!$this->isReadableFromOutside($property)) {
				continue;
			}

			$properties[$propertyName] = $reflection->getProperty($propertyName, $scope);
		}
		return $properties;
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
