<?php declare(strict_types = 1);

namespace App;

use WebChemistry\ConsoleArguments\Attribute\Description;
use WebChemistry\ConsoleArguments\Extension\DefaultValuesProviderInterface;

#[Description('Build application')]
final class BuilderArguments implements DefaultValuesProviderInterface
{

	#[Description('Sets port.')]
	public int $port = 8080;

	#[Description('Sets php memory limit.')]
	public string $memoryLimit = '64M';

	#[Description('Sets nginx client_max_body_size, php post_max_size and upload_max_filesize')]
	public string $httpMaxSize = '8M';

	#[Description('Sets php max execution time.')]
	public int $maxExecutionTime = 30;

	#[Description('Sets php max input time.')]
	public int $maxInputTime = 30;

	#[Description('Optimize php fpm by number of cpu cores.')]
	public int $cpuCores = 2;

	#[Description('Sets fpm max_children')]
	public ?int $maxChildren = null;

	#[Description('Sets fpm max_children')]
	public string $processIdleTimeout = '10s';

	#[Description('Sets fpm max_requests')]
	public int $maxRequests = 200;

	#[Description('Redirects http => https.')]
	public bool $https = false;

	#[Description('Redirects www => non-www.')]
	public bool $nonWww = false;

	#[Description('Cache css and js.')]
	public bool $cacheCssJs = false;

	#[Description('Cache images, icons, video audio, HTC.')]
	public bool $cacheMedia = false;

	#[Description('Enables xdebug.')]
	public bool $xdebug = false;

	#[Description('Enables dev mode.')]
	public bool $dev = false;

	#[Description('Disables nginx.')]
	public bool $disableNginx = false;

	#[Description('Enables gcloud run optimization.')]
	public bool $gcloudRun = false;

	#[Description('Enables opcache preloading.')]
	public string $preload = '/dev/null';

	#[Description('Sets preload user.')]
	public ?string $preloadUser = null;

	#[Description('Enables xdebug profiler.')]
	public string $xdebugProfiler = '/dev/null';

	#[Description('Disables opcache.')]
	public bool $opcacheDisable = false;

	/** @var string[] */
	#[Description('Makes directory.')]
	public array $mkdir = [];

	#[Description('Enables redis extension.')]
	public bool $extRedis = false;

	#[Description('Enables memcached extension.')]
	public bool $extMemcached = false;

	#[Description('Enables memcached extension.')]
	public bool $extSwoole = false;

	#[Description('Enables imagick extension.')]
	public bool $extImagick = false;

	public int $_validateTimestamps = 0;

	public int $_revalidateFreq = 2;

	public function provideDefaultValues(): iterable
	{
		if ($this->dev) {
			yield 'memoryLimit' => '512M';
			yield 'httpMaxSize' => '32M';
			yield 'maxExecutionTime' => 60;
			yield 'cpuCores' => 1;
			yield '_validateTimestamps' => 1;
			yield '_revalidateFreq' => 0;
		}
	}

}
