<?php declare(strict_types = 1);

namespace Tests\Unit;

use PHPStan\Testing\PHPStanTestCase;
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

	private function createHelper(): PhpStanReflectionHelper
	{
		return new PhpStanReflectionHelper();
	}

}
