<?php declare(strict_types = 1);

namespace Tests\Unit;

use PHPStan\PhpDoc\TypeStringResolver;
use PHPStan\Testing\PHPStanTestCase;
use Shredio\PhpStanHelpers\Exception\EmptyTypeException;
use Shredio\PhpStanHelpers\Exception\InvalidTypeException;
use Shredio\PhpStanHelpers\Exception\NonConstantTypeException;
use Shredio\PhpStanHelpers\PhpStanReflectionHelper;
use Tests\Common\AccessObject;

final class PhpStanReflectionHelperTest extends PHPStanTestCase
{

	public function testWritableProperties(): void
	{
		$helper = $this->createHelper();
		$reflectionProvider = self::createReflectionProvider();

		$properties = array_keys(iterator_to_array(
			$helper->getWritablePropertiesFromReflection($reflectionProvider->getClass(AccessObject::class), includeStatic: true),
			true,
		));

		self::assertSame([
			'regularPublic', 'hookSet', 'hookBoth', 'staticPublic',
		], $properties);
	}

	public function testWritablePropertiesNoStatic(): void
	{
		$helper = $this->createHelper();
		$reflectionProvider = self::createReflectionProvider();

		$properties = array_keys(iterator_to_array(
			$helper->getWritablePropertiesFromReflection($reflectionProvider->getClass(AccessObject::class)),
			true,
		));

		self::assertSame([
			'regularPublic', 'hookSet', 'hookBoth',
		], $properties);
	}

	public function testWritablePropertiesPick(): void
	{
		$helper = $this->createHelper();
		$reflectionProvider = self::createReflectionProvider();

		$properties = array_keys(iterator_to_array(
			$helper->getWritablePropertiesFromReflection($reflectionProvider->getClass(AccessObject::class), [
				'regularPublic' => true,
				'staticPublic' => true,
			], includeStatic: true),
			true,
		));

		self::assertSame([
			'regularPublic', 'staticPublic',
		], $properties);
	}

	public function testWritablePropertiesEmptyPick(): void
	{
		$helper = $this->createHelper();
		$reflectionProvider = self::createReflectionProvider();

		$properties = array_keys(iterator_to_array(
			$helper->getWritablePropertiesFromReflection($reflectionProvider->getClass(AccessObject::class), [], includeStatic: true),
			true,
		));

		self::assertSame([], $properties);
	}

	public function testReadableProperties(): void
	{
		$helper = $this->createHelper();
		$reflectionProvider = self::createReflectionProvider();

		$properties = array_keys(iterator_to_array(
			$helper->getReadablePropertiesFromReflection($reflectionProvider->getClass(AccessObject::class), includeStatic: true),
			true,
		));

		self::assertSame([
			'regularPublic', 'readonlyPublic', 'hookGet', 'hookBoth', 'protectedSet', 'privateSet', 'staticPublic',
		], $properties);
	}

	public function testReadablePropertiesNoStatic(): void
	{
		$helper = $this->createHelper();
		$reflectionProvider = self::createReflectionProvider();

		$properties = array_keys(iterator_to_array(
			$helper->getReadablePropertiesFromReflection($reflectionProvider->getClass(AccessObject::class)),
			true,
		));

		self::assertSame([
			'regularPublic', 'readonlyPublic', 'hookGet', 'hookBoth', 'protectedSet', 'privateSet',
		], $properties);
	}

	public function testReadablePropertiesPick(): void
	{
		$helper = $this->createHelper();
		$reflectionProvider = self::createReflectionProvider();

		$properties = array_keys(iterator_to_array(
			$helper->getReadablePropertiesFromReflection($reflectionProvider->getClass(AccessObject::class), [
				'regularPublic' => true,
				'staticPublic' => true,
			], includeStatic: true),
			true,
		));

		self::assertSame([
			'regularPublic', 'staticPublic',
		], $properties);
	}

	public function testReadablePropertiesEmptyPick(): void
	{
		$helper = $this->createHelper();
		$reflectionProvider = self::createReflectionProvider();

		$properties = array_keys(iterator_to_array(
			$helper->getReadablePropertiesFromReflection($reflectionProvider->getClass(AccessObject::class), [], includeStatic: true),
			true,
		));

		self::assertSame([], $properties);
	}

	public function testClassReflectionFromClassStringWithSingleType(): void
	{
		$helper = $this->createHelper();
		$type = $this->createTypeFromString('class-string<\stdClass>');

		$reflection = $helper->getClassReflectionFromClassString($type);
		self::assertNotNull($reflection);
		self::assertSame(\stdClass::class, $reflection->getName());
	}

	public function testClassReflectionFromClassStringWithUnionType(): void
	{
		$helper = $this->createHelper();
		$type = $this->createTypeFromString('class-string<\stdClass|\DOMDocument>');

		self::expectException(InvalidTypeException::class);
		$helper->getClassReflectionFromClassString($type);
	}

	public function testClassReflectionFromClassStringWithEmptyClassString(): void
	{
		$helper = $this->createHelper();
		$type = $this->createTypeFromString('class-string');

		self::expectException(EmptyTypeException::class);
		$helper->getClassReflectionFromClassString($type);
	}

	public function testClassReflectionFromClassStringWithNonClassString(): void
	{
		$helper = $this->createHelper();
		$type = $this->createTypeFromString('string');

		self::expectException(InvalidTypeException::class);
		$helper->getClassReflectionFromClassString($type);
	}

	public function testNonEmptyStringKeyWithTypeFromConstantArrayWithValidArray(): void
	{
		$helper = $this->createHelper();
		$type = $this->createTypeFromString("array{name: string, age: int}");

		$result = $helper->getNonEmptyStringKeyWithTypeFromConstantArray($type);
		self::assertNotNull($result);
		self::assertArrayHasKey('name', $result);
		self::assertArrayHasKey('age', $result);
		self::assertCount(2, $result);
	}

	public function testNonEmptyStringKeyWithTypeFromConstantArrayWithNonArray(): void
	{
		$helper = $this->createHelper();
		$type = $this->createTypeFromString('string');

		self::expectException(InvalidTypeException::class);
		$helper->getNonEmptyStringKeyWithTypeFromConstantArray($type);
	}

	public function testNonEmptyStringKeyWithTypeFromConstantArrayWithNonConstantArray(): void
	{
		$helper = $this->createHelper();
		$type = $this->createTypeFromString('array<string, int>');

		self::expectException(NonConstantTypeException::class);
		$helper->getNonEmptyStringKeyWithTypeFromConstantArray($type);
	}

	public function testNonEmptyStringKeyWithTypeFromConstantArrayWithEmptyStringKeys(): void
	{
		$helper = $this->createHelper();
		$type = $this->createTypeFromString("array{'': string, name: string}");

		$result = $helper->getNonEmptyStringKeyWithTypeFromConstantArray($type);
		self::assertNotNull($result);
		self::assertArrayNotHasKey('', $result);
		self::assertArrayHasKey('name', $result);
		self::assertCount(1, $result);
	}

	public function testNonEmptyStringKeyWithTypeFromConstantArrayWithIntegerKeys(): void
	{
		$helper = $this->createHelper();
		$type = $this->createTypeFromString('array{0: string, name: string}');

		$result = $helper->getNonEmptyStringKeyWithTypeFromConstantArray($type);
		self::assertNotNull($result);
		self::assertArrayNotHasKey('0', $result);
		self::assertArrayHasKey('name', $result);
		self::assertCount(1, $result);
	}

	public function testTrueOrFalseFromConstantBooleanWithTrue(): void
	{
		$helper = $this->createHelper();
		$type = $this->createTypeFromString('true');

		$result = $helper->getTrueOrFalseFromConstantBoolean($type);
		self::assertTrue($result);
	}

	public function testTrueOrFalseFromConstantBooleanWithFalse(): void
	{
		$helper = $this->createHelper();
		$type = $this->createTypeFromString('false');

		$result = $helper->getTrueOrFalseFromConstantBoolean($type);
		self::assertFalse($result);
	}

	public function testTrueOrFalseFromConstantBooleanWithNonBoolean(): void
	{
		$helper = $this->createHelper();
		$type = $this->createTypeFromString('string');

		self::expectException(InvalidTypeException::class);
		$helper->getTrueOrFalseFromConstantBoolean($type);
	}

	public function testTrueOrFalseFromConstantBooleanWithNonConstantBoolean(): void
	{
		$helper = $this->createHelper();
		$type = $this->createTypeFromString('bool');

		self::expectException(NonConstantTypeException::class);
		$helper->getTrueOrFalseFromConstantBoolean($type);
	}

	public function testNonEmptyStringsFromStringTypeWithValidStrings(): void
	{
		$helper = $this->createHelper();
		$type = $this->createTypeFromString("'hello'|'world'");

		$result = $helper->getNonEmptyStringsFromStringType($type);
		self::assertNotNull($result);
		self::assertSame(['hello', 'world'], $result);
	}

	public function testNonEmptyStringsFromStringTypeWithSingleString(): void
	{
		$helper = $this->createHelper();
		$type = $this->createTypeFromString("'hello'");

		$result = $helper->getNonEmptyStringsFromStringType($type);
		self::assertNotNull($result);
		self::assertSame(['hello'], $result);
	}

	public function testNonEmptyStringsFromStringTypeWithEmptyStringFiltered(): void
	{
		$helper = $this->createHelper();
		$type = $this->createTypeFromString("''|'hello'");

		$result = $helper->getNonEmptyStringsFromStringType($type);
		self::assertNotNull($result);
		self::assertSame(['hello'], $result);
	}

	public function testNonEmptyStringsFromStringTypeWithOnlyEmptyString(): void
	{
		$helper = $this->createHelper();
		$type = $this->createTypeFromString("''");

		self::expectException(EmptyTypeException::class);
		$helper->getNonEmptyStringsFromStringType($type);
	}

	public function testNonEmptyStringsFromStringTypeWithNonString(): void
	{
		$helper = $this->createHelper();
		$type = $this->createTypeFromString('int');

		self::expectException(InvalidTypeException::class);
		$helper->getNonEmptyStringsFromStringType($type);
	}

	public function testNonEmptyStringsFromStringTypeWithNonConstantString(): void
	{
		$helper = $this->createHelper();
		$type = $this->createTypeFromString('string');

		self::expectException(NonConstantTypeException::class);
		$helper->getNonEmptyStringsFromStringType($type);
	}

	public function testValueTypesFromConstantListWithValidList(): void
	{
		$helper = $this->createHelper();
		$type = $this->createTypeFromString("list{'hello', 'world'}");

		$result = $helper->getValueTypesFromConstantList($type);
		self::assertCount(2, $result);
	}

	public function testValueTypesFromConstantListWithEmptyList(): void
	{
		$helper = $this->createHelper();
		$type = $this->createTypeFromString('list{}');

		$result = $helper->getValueTypesFromConstantList($type);
		self::assertCount(0, $result);
	}

	public function testValueTypesFromConstantListWithNonList(): void
	{
		$helper = $this->createHelper();
		$type = $this->createTypeFromString("array{0: 'hello', foo: 'bar'}");

		self::expectException(InvalidTypeException::class);
		$helper->getValueTypesFromConstantList($type);
	}

	public function testValueTypesFromConstantListWithNonArray(): void
	{
		$helper = $this->createHelper();
		$type = $this->createTypeFromString('string');

		self::expectException(InvalidTypeException::class);
		$helper->getValueTypesFromConstantList($type);
	}

	public function testValueTypesFromConstantListWithNonConstantList(): void
	{
		$helper = $this->createHelper();
		$type = $this->createTypeFromString('list<string>');

		self::expectException(NonConstantTypeException::class);
		$helper->getValueTypesFromConstantList($type);
	}

	public function testNonEmptyStringsFromConstantArrayTypeWithValidArray(): void
	{
		$helper = $this->createHelper();
		$type = $this->createTypeFromString("array{'hello', 'world'}");

		$result = $helper->getNonEmptyStringsFromConstantArrayType($type);
		self::assertSame(['hello', 'world'], $result);
	}

	public function testNonEmptyStringsFromConstantArrayTypeWithMixedTypes(): void
	{
		$helper = $this->createHelper();
		$type = $this->createTypeFromString("array{'hello', 42, 'world'}");

		$result = $helper->getNonEmptyStringsFromConstantArrayType($type);
		self::assertSame(['hello', 'world'], $result);
	}

	public function testNonEmptyStringsFromConstantArrayTypeWithEmptyStringsFiltered(): void
	{
		$helper = $this->createHelper();
		$type = $this->createTypeFromString("array{'', 'hello', ''}");

		$result = $helper->getNonEmptyStringsFromConstantArrayType($type);
		self::assertSame(['hello'], $result);
	}

	public function testNonEmptyStringsFromConstantArrayTypeWithOnlyEmptyStrings(): void
	{
		$helper = $this->createHelper();
		$type = $this->createTypeFromString("array{'', ''}");

		self::expectException(EmptyTypeException::class);
		$helper->getNonEmptyStringsFromConstantArrayType($type);
	}

	public function testNonEmptyStringsFromConstantArrayTypeWithNonArray(): void
	{
		$helper = $this->createHelper();
		$type = $this->createTypeFromString('string');

		self::expectException(InvalidTypeException::class);
		$helper->getNonEmptyStringsFromConstantArrayType($type);
	}

	public function testNonEmptyStringsFromConstantArrayTypeWithNonConstantArray(): void
	{
		$helper = $this->createHelper();
		$type = $this->createTypeFromString('array<int, string>');

		self::expectException(NonConstantTypeException::class);
		$helper->getNonEmptyStringsFromConstantArrayType($type);
	}

	public function testNonEmptyStringsFromConstantArrayTypeWithEmptyArray(): void
	{
		$helper = $this->createHelper();
		$type = $this->createTypeFromString('array{}');

		self::expectException(EmptyTypeException::class);
		$helper->getNonEmptyStringsFromConstantArrayType($type);
	}

	private function createHelper(): PhpStanReflectionHelper
	{
		return new PhpStanReflectionHelper();
	}

	private function createTypeFromString(string $string)
	{
		$resolver = self::getContainer()->getByType(TypeStringResolver::class);
		return $resolver->resolve($string);
	}

}
