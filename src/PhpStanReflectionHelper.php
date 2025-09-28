<?php declare(strict_types = 1);

namespace Shredio\PhpStanHelpers;

use PHPStan\Analyser\OutOfClassScope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ExtendedMethodReflection;
use PHPStan\Reflection\ExtendedParameterReflection;
use PHPStan\Reflection\ExtendedPropertyReflection;
use PHPStan\Type\Type;
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
	 * @param array<string, bool> $pick
	 * @return iterable<string, ExtendedPropertyReflection>
	 */
	public function getWritablePropertiesFromReflection(ClassReflection $reflection, array $pick = []): iterable
	{
		foreach ($reflection->getNativeReflection()->getProperties() as $property) {
			if ($property->isStatic()) {
				continue;
			}
			$propertyName = $property->getName();
			if ($pick !== [] && !isset($pick[$propertyName])) {
				continue;
			}

			$propertyReflection = $reflection->getProperty($propertyName, new OutOfClassScope());
			if (!$propertyReflection->isWritable()) {
				continue;
			}

			yield $propertyName => $propertyReflection;
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
	 * @param array<string, bool> $pick
	 * @return iterable<string, ExtendedPropertyReflection>
	 */
	public function getReadablePropertiesFromReflection(ClassReflection $reflection, array $pick = []): iterable
	{
		foreach ($reflection->getNativeReflection()->getProperties() as $property) {
			if ($property->isStatic()) {
				continue;
			}
			$propertyName = $property->getName();
			if ($pick !== [] && !isset($pick[$propertyName])) {
				continue;
			}

			$propertyReflection = $reflection->getProperty($propertyName, new OutOfClassScope());
			if (!$propertyReflection->isReadable()) {
				continue;
			}

			yield $propertyName => $propertyReflection;
		}
	}

}
