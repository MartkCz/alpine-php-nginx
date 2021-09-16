<?php declare(strict_types = 1);

namespace App;

use Nette\Schema\Expect;
use Nette\Schema\Processor;
use Nette\Utils\FileSystem;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class BuilderCommand extends Command
{

	private const NGINX_FILE = '/etc/nginx/nginx.conf';
	private const PHP_FILE = '/etc/php8/conf.d/99_settings.ini';
	private const SUPERVISORD_FILE = '/etc/supervisor/supervisord.conf';
	private const PHP_FPM_FILE = '/etc/php8/php-fpm.conf';
	private const FASTCGI_PARAMS_FILE = '/etc/nginx/includes/fastcgi-params.conf';

	private const PHP_FPM_TEMPLATE = __DIR__ . '/../assets/php-fpm/php-fpm.conf.template';
	private const PHP_TEMPLATE = __DIR__ . '/../assets/php/99_settings.ini.template';
	private const FASTCGI_PARAMS_TEMPLATE = __DIR__ . '/../assets/nginx/fastcgi_params.conf.template';

	protected static string $defaultName = 'builder';

	private static OutputInterface $output;

	protected function configure(): void
	{
		$this->setDescription('Build application')
			->addOption('non-www', null, InputOption::VALUE_NONE, 'Redirects www => non-www.')
			->addOption('port', null, InputOption::VALUE_REQUIRED, 'Sets port.', 8080)
			->addOption('https', null, InputOption::VALUE_NONE, 'Redirects http => https.')
			->addOption('cache-css-js', null, InputOption::VALUE_NONE, 'Cache css and js for long time.')
			->addOption('cache-media', null, InputOption::VALUE_NONE, 'Cache images, icons, video audio, HTC for long time.')
			->addOption('xdebug', null, InputOption::VALUE_NONE, 'Enables xdebug.')
			->addOption('xdebug-profiler', null, InputOption::VALUE_REQUIRED, 'Enables xdebug profiler.', '/dev/null')
			->addOption('memory-limit', null, InputOption::VALUE_REQUIRED, 'Sets php memory limit.', '64M')
			->addOption('max-execution-time', null, InputOption::VALUE_REQUIRED, 'Sets php max execution time.', 30)
			->addOption('max-input-time', null, InputOption::VALUE_REQUIRED, 'Sets php max input time.', 30)
			->addOption('disable-nginx', null, InputOption::VALUE_NONE, 'Disables nginx.')
			->addOption('gcloud-run', null, InputOption::VALUE_NONE, 'Enables gcloud run optimization.')
			->addOption('mkdir', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Makes directory.')
			->addOption('dev', null, InputOption::VALUE_NONE, 'Enables dev mode.')
			->addOption('preload', null, InputOption::VALUE_REQUIRED, 'Enables opcache preloading.', '/dev/null')
			->addOption('preload-user', null, InputOption::VALUE_REQUIRED, 'Sets preload user')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$arguments = $this->getArguments($input);

		self::$output = $output;

		$phpTemplate = new FileTemplate(self::PHP_TEMPLATE);
		$phpFpmTemplate = new FileTemplate(self::PHP_FPM_TEMPLATE);
		$nginxTemplate = new FileTemplate(__DIR__ . '/../assets/nginx/nginx.conf.template');
		$supervisordTemplate = new FileTemplate(__DIR__ . '/../assets/supervisord/supervisord.conf.template');
		$fastCgiParamsTemplate = new FileTemplate(self::FASTCGI_PARAMS_TEMPLATE);

		if ($arguments->dev) {
			$this->log('Enabling dev mode.');
		}

		$this->preparePhp($arguments, $phpTemplate);
		$this->prepareNginx($arguments, $nginxTemplate);
		$this->prepareSupervisord($arguments, $supervisordTemplate);
		$this->preparePhpFpm($arguments, $phpFpmTemplate);
		$this->prepareFastCgi($fastCgiParamsTemplate);

		// Gcloud run
		if ($arguments->gcloudRun) {
			$this->prepareGcloudRun($phpFpmTemplate, $phpTemplate, $fastCgiParamsTemplate);
		}

		// mkdir
		foreach ($arguments->mkdir as $path) {
			$this->log(sprintf('Creating directory with path %s.', $path));

			FileSystem::createDir($path);
		}

		$output->writeln('<info>Writing config files.</info>');

		$phpTemplate->renderToFile(self::PHP_FILE);
		$nginxTemplate->renderToFile(self::NGINX_FILE);
		$supervisordTemplate->renderToFile(self::SUPERVISORD_FILE);
		$phpFpmTemplate->renderToFile(self::PHP_FPM_FILE);
		$fastCgiParamsTemplate->renderToFile(self::FASTCGI_PARAMS_FILE);

		return self::SUCCESS;
	}

	private function prepareNginx(BuilderArguments $arguments, FileTemplate $nginxTemplate): void
	{
		if ($arguments->https) {
			$this->log('Enabling http => https redirection.');

			$nginxTemplate->addSectionFromFile('server', __DIR__ . '/../assets/nginx-https.conf');
		}

		if ($arguments->nonWww) {
			$this->log('Enabling www => non-www redirection.');

			$nginxTemplate->addSectionFromFile('server', __DIR__ . '/../assets/nginx-non-www.conf');
		}

		if ($arguments->cacheCssJs) {
			$this->log('Enabling cache css and js.');

			$nginxTemplate->addSectionFromFile('server', __DIR__ . '/../assets/nginx/nginx-cache-css-js-long.conf');
		}

		if ($arguments->cacheMedia) {
			$this->log('Enabling cache media.');

			$nginxTemplate->addSectionFromFile('server', __DIR__ . '/../assets/nginx/nginx-cache-media-long.conf');
		}
	}

	private function preparePhp(BuilderArguments $arguments, FileTemplate $phpTemplate): void
	{
		if ($arguments->port !== 8080) {
			$this->log(sprintf('Setting port to %s.', $arguments->port));

			FileTemplate::replace(self::NGINX_FILE, 'listen 8080;', sprintf('listen %s;', $arguments->port));
		}

		if ($arguments->xdebug) {
			$this->log('Enabling xdebug.');

			$phpTemplate->addSection('append', 'zend_extension=xdebug.so');
		}

		if ($arguments->xdebugProfiler !== '/dev/null') {
			$this->log('Enabling xdebug profiler.');

			$phpTemplate->addSection(
				'append',
				FileTemplate::renderStatic(__DIR__ . '/../assets/php/xdebug.profiler.ini.template', [
					'output_dir' => $arguments->xdebugProfiler,
				])
			);
		}

		$phpTemplate->addVariable('validate_timestamps', $arguments->dev ? 1 : 0);

		$phpTemplate->addVariable('max_execution_time', $arguments->maxExecutionTime);
		$phpTemplate->addVariable('max_input_time', $arguments->maxInputTime);
		$phpTemplate->addVariable('memory_limit', $arguments->memoryLimit);

		$phpTemplate->addVariable('opcache.memory_consumption', 128);
		$phpTemplate->addVariable('opcache.enable_cli', 0);

		if ($arguments->preload !== '/dev/null') {
			$this->log('Enables preload.');

			$phpTemplate->addSection('append', 'opcache.preload=' . $arguments->preload);

			if ($arguments->preloadUser) {
				$phpTemplate->addSection('append', 'opcache.preload_user=' . $arguments->preloadUser);
			}
		}
	}

	private function preparePhpFpm(BuilderArguments $arguments, FileTemplate $phpFpmTemplate): void
	{
		$phpFpmTemplate->addVariable('pm', 'dynamic');
		$phpFpmTemplate->addVariable('max_children', 500);
	}

	private function prepareSupervisord(BuilderArguments $arguments, FileTemplate $supervisordTemplate): void
	{
		$supervisordTemplate->addSectionFromFile('append', __DIR__ . '/../assets/supervisord/php.conf');

		if (!$arguments->disableNginx) {
			$supervisordTemplate->addSectionFromFile('append', __DIR__ . '/../assets/supervisord/nginx.conf');
		} else {
			$this->log('Disabling nginx.');
		}
	}

	private function prepareGcloudRun(FileTemplate $phpFpmTemplate, FileTemplate $phpTemplate, FileTemplate $fastCgiParamsTemplate): void
	{
		$phpFpmTemplate->addVariable('pm', 'static');
		$phpFpmTemplate->addVariable('max_children', 30);

		$phpTemplate->addVariable('memory_limit', '512M');
		$phpTemplate->addVariable('opcache.memory_consumption', 256);

		$fastCgiParamsTemplate->addVariable('server_port', 443);
	}

	private function prepareFastCgi(FileTemplate $fastCgiParamsTemplate): void
	{
		$fastCgiParamsTemplate->addVariable('server_port', '$server_port');
	}

	private function getArguments(InputInterface $input): BuilderArguments
	{
		$arguments = new BuilderArguments();
		/** @var BuilderArguments $arguments */
		$arguments = (new Processor())->process(
			Expect::from($arguments, $arguments->getSchema()),
			$arguments->getSchemaMapping(fn (string $name) => $input->getOption($name)),
		);
		$arguments->validate();

		return $arguments;
	}

	private function log(string $message)
	{
		self::$output->writeln(sprintf('<comment>%s</comment>', $message));
	}

}
