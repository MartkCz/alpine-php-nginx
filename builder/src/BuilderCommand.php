<?php declare(strict_types = 1);

namespace App;

use Exception;
use WebChemistry\ConsoleArguments\BaseCommand;
use Nette\Utils\FileSystem;

final class BuilderCommand extends BaseCommand
{

	private const NGINX_FILE = '/etc/nginx/nginx.conf';
	private const PHP_FILE = '/etc/php8/conf.d/99_settings.ini';
	private const SUPERVISORD_FILE = '/etc/supervisor/supervisord.conf';
	private const PHP_FPM_FILE = '/etc/php8/php-fpm.conf';
	private const FASTCGI_PARAMS_FILE = '/etc/nginx/includes/fastcgi-params.conf';

	private const PHP_FPM_TEMPLATE = __DIR__ . '/../assets/php-fpm/php-fpm.conf.template';
	private const PHP_TEMPLATE = __DIR__ . '/../assets/php/99_settings.ini.template';
	private const FASTCGI_PARAMS_TEMPLATE = __DIR__ . '/../assets/nginx/fastcgi_params.conf.template';

	private const PHP_CONF = [
		'memcached' => '/etc/php8/conf.d/20_memcached.ini',
		'redis' => '/etc/php8/conf.d/20_redis.ini',
		'swoole' => '/etc/php8/conf.d/00_swoole.ini',
		'imagick' => '/etc/php8/conf.d/00_imagick.ini',
	];

	protected static string $defaultName = 'builder';

	protected BuilderArguments $arguments;

	protected function exec(): void
	{
		$phpTemplate = new FileTemplate(self::PHP_TEMPLATE);
		$phpFpmTemplate = new FileTemplate(self::PHP_FPM_TEMPLATE);
		$nginxTemplate = new FileTemplate(__DIR__ . '/../assets/nginx/nginx.conf.template');
		$supervisordTemplate = new FileTemplate(__DIR__ . '/../assets/supervisord/supervisord.conf.template');
		$fastCgiParamsTemplate = new FileTemplate(self::FASTCGI_PARAMS_TEMPLATE);

		if ($this->arguments->dev) {
			$this->log('Enabling dev mode.');
		}

		$this->preparePhp($phpTemplate);
		$this->prepareNginx($nginxTemplate);
		$this->prepareSupervisord($supervisordTemplate);
		$this->preparePhpFpm($phpFpmTemplate);
		$this->prepareFastCgi($fastCgiParamsTemplate);

		// Gcloud run
		if ($this->arguments->gcloudRun) {
			$this->prepareGcloudRun($phpFpmTemplate, $phpTemplate, $fastCgiParamsTemplate);
		}

		// mkdir
		foreach ($this->arguments->mkdir as $path) {
			$this->log(sprintf('Creating directory with path %s.', $path));

			FileSystem::createDir($path);
		}

		$this->output->writeln('<info>Writing config files.</info>');

		$phpTemplate->renderToFile(self::PHP_FILE);
		$nginxTemplate->renderToFile(self::NGINX_FILE);
		$supervisordTemplate->renderToFile(self::SUPERVISORD_FILE);
		$phpFpmTemplate->renderToFile(self::PHP_FPM_FILE);
		$fastCgiParamsTemplate->renderToFile(self::FASTCGI_PARAMS_FILE);
	}

	private function prepareNginx(FileTemplate $nginxTemplate): void
	{
		if ($this->arguments->https) {
			$this->log('Enabling http => https redirection.');

			$nginxTemplate->addSectionFromFile('server', __DIR__ . '/../assets/nginx-https.conf');
		}

		if ($this->arguments->nonWww) {
			$this->log('Enabling www => non-www redirection.');

			$nginxTemplate->addSectionFromFile('server', __DIR__ . '/../assets/nginx-non-www.conf');
		}

		if ($this->arguments->cacheCssJs) {
			$this->log('Enabling cache css and js.');

			$nginxTemplate->addSectionFromFile('server', __DIR__ . '/../assets/nginx/nginx-cache-css-js-long.conf');
		}

		if ($this->arguments->cacheMedia) {
			$this->log('Enabling cache media.');

			$nginxTemplate->addSectionFromFile('server', __DIR__ . '/../assets/nginx/nginx-cache-media-long.conf');
		}

		$nginxTemplate->addVariable('client_max_body_size', $this->arguments->httpMaxSize);
	}

	private function preparePhp(FileTemplate $phpTemplate): void
	{
		if ($this->arguments->port !== 8080) {
			$this->log(sprintf('Setting port to %s.', $this->arguments->port));

			FileTemplate::replace(self::NGINX_FILE, 'listen 8080;', sprintf('listen %s;', $this->arguments->port));
		}

		if ($this->arguments->xdebug) {
			$this->log('Enabling xdebug.');

			$phpTemplate->addSection('append', 'zend_extension=xdebug.so');
		}

		if ($this->arguments->xdebugProfiler !== '/dev/null') {
			$this->log('Enabling xdebug profiler.');

			$phpTemplate->addSection(
				'append',
				FileTemplate::renderStatic(__DIR__ . '/../assets/php/xdebug.profiler.ini.template', [
					'output_dir' => $this->arguments->xdebugProfiler,
				])
			);
		}

		$phpTemplate->addVariable('max_execution_time', $this->arguments->maxExecutionTime);
		$phpTemplate->addVariable('max_input_time', $this->arguments->maxInputTime);
		$phpTemplate->addVariable('memory_limit', $this->arguments->memoryLimit);
		$phpTemplate->addVariable('upload_max_filesize', $this->arguments->httpMaxSize);
		$phpTemplate->addVariable('post_max_size', $this->arguments->httpMaxSize);

		$phpTemplate->addVariable('opcache.validate_timestamps', $this->arguments->_validateTimestamps);
		$phpTemplate->addVariable('opcache.enable', $this->arguments->opcacheDisable ? 0 : 1);
		$phpTemplate->addVariable('opcache.enable_cli', 0);
		$phpTemplate->addVariable('opcache.memory_consumption', 128);
		$phpTemplate->addVariable('opcache.revalidate_freq', $this->arguments->_revalidateFreq); // 2 => default

		if ($this->arguments->preload !== '/dev/null') {
			$this->log('Enables preload.');

			$phpTemplate->addSection('append', 'opcache.preload=' . $this->arguments->preload);

			if ($this->arguments->preloadUser) {
				$phpTemplate->addSection('append', 'opcache.preload_user=' . $this->arguments->preloadUser);
			}
		}

		$extensions = [
			'memcached' => $this->arguments->extMemcached,
			'swoole' => $this->arguments->extSwoole,
			'redis' => $this->arguments->extRedis,
			'imagick' => $this->arguments->extImagick,
		];

		foreach ($extensions as $extension => $enabled) {
			if (!$enabled) {
				$filePath = self::PHP_CONF[$extension];
				if (is_file($filePath)) {
					FileSystem::delete($filePath);
				} else {
					throw new Exception('Cannot delete file ' . $filePath);
				}
			}
		}

	}

	private function preparePhpFpm(FileTemplate $phpFpmTemplate): void
	{
		$cores = max($this->arguments->cpuCores, 1);
		$startServers = $cores * 4;
		$maxChildren = max($this->arguments->maxChildren ?: $startServers * 2, $startServers);

		$phpFpmTemplate->addVariable('fpm.pm', 'dynamic');
		$phpFpmTemplate->addVariable('fpm.max_children', $maxChildren);
		$phpFpmTemplate->addVariable('fpm.start_servers', $startServers);
		$phpFpmTemplate->addVariable('fpm.min_spare_servers', $cores * 2);
		$phpFpmTemplate->addVariable('fpm.max_spare_servers', $startServers);
		$phpFpmTemplate->addVariable('fpm.process_idle_timeout', $this->arguments->processIdleTimeout);
		$phpFpmTemplate->addVariable('fpm.max_requests', $this->arguments->maxRequests);
	}

	private function prepareSupervisord(FileTemplate $supervisordTemplate): void
	{
		$supervisordTemplate->addSectionFromFile('append', __DIR__ . '/../assets/supervisord/php.conf');

		if (!$this->arguments->disableNginx) {
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

	private function log(string $message)
	{
		$this->output->writeln(sprintf('<comment>%s</comment>', $message));
	}

}
