<?php declare(strict_types = 1);

namespace JedenWeb\TesterUtils;

use Nette;
use Nette\DI\Container;
use Nette\Http\Session;

/**
 * @author Filip Procházka <filip@prochazka.su>
 */
trait CompiledContainer
{

	/** @var Container */
	private $container;

	protected function getContainer(): Container
	{
		if ($this->container === null) {
			$this->container = $this->createContainer();
		}

		return $this->container;
	}

	protected function isContainerCreated(): bool
	{
		return $this->container !== null;
	}

	protected function refreshContainer(): void
	{
		$container = $this->getContainer();

		/** @var Session $session */
		$session = $container->getByType(Session::class);

		if ($session && $session->isStarted()) {
			$session->close();
		}

		$this->container = new $container();
		$this->container->initialize();
	}

	protected function tearDownContainer(): bool
	{
		if ($this->container) {
			/** @var Session $session */
			$session = $this->getContainer()->getByType(Session::class);
			if ($session->isStarted()) {
				$session->destroy();
			}

			$this->container = null;

			return true;
		}

		return true;
	}

	protected function doCreateConfiguration(): Nette\Configurator
	{
		$config = new Nette\Configurator();
		$config->addParameters([
			// vendor/kdyby/tester-extras/src
			'rootDir' => $rootDir = __DIR__ . '/..',
			'appDir' => $rootDir . '/src',
			'wwwDir' => $rootDir . '/www',
		]);

		// shared compiled container for faster tests
		$config->setTempDirectory(TEMP_DIR);

		return $config;
	}

	/**
	 * @param string[] $configs
	 */
	protected function createContainer(array $configs = []): Container
	{
		$config = $this->doCreateConfiguration();

		foreach ($configs as $file) {
			$config->addConfig($file);
		}

		/** @var Container $container */
		$container = $config->createContainer();

		return $container;
	}

	/**
	 * @return object
	 */
	public function getService(string $type)
	{
		$container = $this->getContainer();

		$object = $container->getByType($type, true);

		if ($object) {
			return $object;
		}

		return $container->createInstance($type);
	}

}
