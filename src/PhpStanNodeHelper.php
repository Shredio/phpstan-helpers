<?php declare(strict_types = 1);

namespace Shredio\PhpStanHelpers;

use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Type\Type;

/**
 * @api
 */
final readonly class PhpStanNodeHelper
{

	public function getMethodName(MethodCall $node): ?string
	{
		if ($node->name instanceof Identifier) {
			return $node->name->toString();
		}

		return null;
	}

	/**
	 * @return list<ClassReflection>
	 */
	public function getClassReflectionsFromMethodCall(MethodCall $node, Scope $scope): array
	{
		$type = $scope->getType($node->var);
		if (!$type->isObject()->yes()) {
			return [];
		}

		return $type->getObjectClassReflections();
	}

	/**
	 * @return array<int|non-empty-string, Type>
	 */
	public function getArgumentTypes(MethodCall $node, Scope $scope): array
	{
		$arguments = [];
		foreach ($node->getArgs() as $pos => $arg) {
			if ($arg->name !== null) {
				$arguments[$arg->name->toString()] = $scope->getType($arg->value);
			} else {
				$arguments[$pos] = $scope->getType($arg->value);
			}
		}
		/** @var array<int|non-empty-string, Type> */
		return $arguments;
	}

}
