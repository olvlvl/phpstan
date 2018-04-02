<?php declare(strict_types = 1);

namespace PHPStan\Rules\Comparison;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Analyser\SpecifiedTypes;
use PHPStan\Analyser\TypeSpecifier;
use PHPStan\Analyser\TypeSpecifierContext;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Tests\AssertionClassMethodTypeSpecifyingExtension;
use PHPStan\Type\MethodTypeSpecifyingExtension;

class ImpossibleCheckTypeMethodCallRuleTest extends \PHPStan\Testing\RuleTestCase
{

	public function getRule(): \PHPStan\Rules\Rule
	{
		return new ImpossibleCheckTypeMethodCallRule(true);
	}

	/**
	 * @return MethodTypeSpecifyingExtension[]
	 */
	protected function getMethodTypeSpecifyingExtensions(): array
	{
		$typeSpecifier = $this->createTypeSpecifier(
			new \PhpParser\PrettyPrinter\Standard(),
			$this->createBroker(),
			[],
			[]
		);
		return [
			new AssertionClassMethodTypeSpecifyingExtension(null),
			new class($typeSpecifier) implements MethodTypeSpecifyingExtension {

				/** @var TypeSpecifier */
				private $typeSpecifier;

				public function __construct(TypeSpecifier $typeSpecifier)
				{
					$this->typeSpecifier = $typeSpecifier;
				}

				public function getClass(): string
				{
					return \PHPStan\Tests\AssertionClass::class;
				}

				public function isMethodSupported(
					MethodReflection $methodReflection,
					MethodCall $node,
					TypeSpecifierContext $context
				): bool
				{
					return $methodReflection->getName() === 'assertNotInt'
						&& count($node->args) > 0;
				}

				public function specifyTypes(
					MethodReflection $methodReflection,
					MethodCall $node,
					Scope $scope,
					TypeSpecifierContext $context
				): SpecifiedTypes
				{
					return $this->typeSpecifier->specifyTypesInCondition(
						$scope,
						new \PhpParser\Node\Expr\BooleanNot(
							new \PhpParser\Node\Expr\FuncCall(
								new \PhpParser\Node\Name('is_int'),
								[
									$node->args[0],
								]
							)
						),
						TypeSpecifierContext::createTruthy()
					);
				}

			},
		];
	}

	public function testRule(): void
	{
		$this->analyse([__DIR__ . '/data/impossible-method-call.php'], [
			[
				'Call to method PHPStan\Tests\AssertionClass::assertString() will always evaluate to true.',
				14,
			],
			[
				'Call to method PHPStan\Tests\AssertionClass::assertString() will always evaluate to false.',
				15,
			],
			[
				'Call to method PHPStan\Tests\AssertionClass::assertNotInt() will always evaluate to false.',
				30,
			],
			[
				'Call to method PHPStan\Tests\AssertionClass::assertNotInt() will always evaluate to true.',
				36,
			],
		]);
	}

}
