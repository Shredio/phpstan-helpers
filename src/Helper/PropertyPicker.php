<?php declare(strict_types = 1);

namespace Shredio\PhpStanHelpers\Helper;

use LogicException;
use Shredio\PhpStanHelpers\Exception\CannotCombinePickWithOmitException;

/**
 * @api
 */
final readonly class PropertyPicker
{

	/** @var array<string, bool>|null */
	private ?array $pick;

	/** @var array<string, bool>|null */
	private ?array $omit;


	/**
	 * @param list<string> $pick
	 * @param list<string> $omit
	 *
	 * @throws CannotCombinePickWithOmitException
	 */
	public function __construct(?array $pick = null, ?array $omit = null)
	{
		if ($pick !== null) {
			$this->pick = array_fill_keys($pick, true);
		} else {
			$this->pick = null;
		}

		if ($omit !== null) {
			if ($this->pick !== null) {
				throw new CannotCombinePickWithOmitException();
			}

			$this->omit = array_fill_keys($omit, true);
		} else {
			$this->omit = null;
		}
	}

	public function shouldPick(string $property): bool
	{
		if ($this->pick !== null) {
			return isset($this->pick[$property]);
		}
		if ($this->omit !== null) {
			return !isset($this->omit[$property]);
		}

		return true;
	}

	public static function empty(): self
	{
		try {
			return new self();
		} catch (CannotCombinePickWithOmitException) {
			throw new LogicException('This should never happen');
		}
	}

}
