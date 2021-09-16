<?php declare(strict_types = 1);

namespace App;

use Nette\Schema\Expect;
use ReflectionClass;

final class BuilderArguments
{

	public int $port = 8080;

	public string $memoryLimit = '64M';

	public int $maxExecutionTime = 30;

	public int $maxInputTime = 30;

	public bool $https = false;

	public bool $nonWww = false;

	public bool $cacheCssJs = false;

	public bool $cacheMedia = false;

	public bool $xdebug = false;

	public bool $dev = false;

	public bool $disableNginx = false;

	public bool $gcloudRun = false;

	public string $preload = '/dev/null';

	public ?string $preloadUser = null;

	public string $xdebugProfiler = '/dev/null';

	/** @var string[] */
	public array $mkdir = [];

	public function getSchema(): array
	{
		return [
			'port' => Expect::type('numericint')->castTo('int'),
			'maxExecutionTime' => Expect::type('numericint')->castTo('int'),
			'maxInputTime' => Expect::type('numericint')->castTo('int'),
		];
	}

	public function getSchemaMapping(callable $valueGetter): array
	{
		$reflection = new ReflectionClass($this);
		$mapping = [];
		foreach ($reflection->getProperties() as $property) {
			$name = preg_replace_callback(
				'#([A-Z])#',
				fn (array $matches) => '-' . strtolower($matches[1]),
				$property->getName(),
			);

			$mapping[$property->getName()] = $valueGetter($name);
		}

		return $mapping;
	}

	public function validate(): void
	{
	}

}
