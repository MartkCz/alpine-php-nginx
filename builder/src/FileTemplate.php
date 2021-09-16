<?php declare(strict_types = 1);

namespace App;

use LogicException;
use Nette\Utils\FileSystem;

final class FileTemplate
{

	public function __construct(
		private string $template,
		private array $variables = [],
	)
	{
	}

	public function addSection(string $name, mixed $value): self
	{
		$this->variables['section:' . $name] ??= '';
		$this->variables['section:' . $name] .= $value . "\n";

		return $this;
	}

	public function addSectionFromFile(string $name, string $file): self
	{
		$this->addSection($name, FileSystem::read($file));

		return $this;
	}

	public function addVariable(string $name, mixed $value): self
	{
		$this->variables[$name] = $value;

		return $this;
	}

	public function render(): string
	{
		$contents = FileSystem::read($this->template);

		return preg_replace_callback('#\$\{([a-zA-Z0-9_:.-]+)\}#', function (array $matches) use (&$unused): string {
			if (!isset($this->variables[$matches[1]])) {
				if (!str_contains($matches[1], ':')) {
					throw new LogicException(sprintf('Variable %s not exists.', $matches[1]));
				}

				return '';
			}

			return (string) $this->variables[$matches[1]];
		}, $contents);
	}

	public function renderToFile(string $file): void
	{
		FileSystem::write($file, $this->render());
	}

	public static function renderStatic(string $template, array $variables): string
	{
		return (new self($template, $variables))->render();
	}

	public static function replace(string $file, string $search, string $replace): void
	{
		FileSystem::write($file, str_replace($search, $replace, FileSystem::read($file)));
	}

}
