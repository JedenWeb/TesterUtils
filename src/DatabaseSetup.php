<?php declare(strict_types = 1);

namespace JedenWeb\TesterUtils;

use Kdyby\Doctrine\Connection;
use Kdyby\Doctrine\Helpers;
use Nette\DI\Container;
use Nette\Utils\Finder;
use Tester\Assert;

/**
 * @author Pavel Jurásek
 */
trait DatabaseSetup
{

	use CompiledContainer {
		createContainer as parentCreateContainer;
	}

	/** @var string|null */
	protected $databaseName;

	/**
	 * @param string[] $configs
	 */
	protected function createContainer(array $configs = []): Container
	{
		$container = $this->parentCreateContainer($configs);

		/** @var ConnectionMock $db */
		$db = $container->getByType(Connection::class);
		if (!$db instanceof ConnectionMock) {
			throw new \LogicException('Connection service should be instance of ConnectionMock');
		}

		$db->onConnect[] = function (Connection $db) use ($container) {
			if ($this->databaseName !== null) {
				return;
			}

			try {
				$this->setupDatabase($db);

			} catch (\Throwable $e) {
				Assert::fail($e->getMessage());
			}
		};

		$db->connect();

		return $container;
	}

	/**
	 * @return string[]
	 */
	abstract public function getSqls(): array;

	/**
	 * @return string[]
	 */
	public function getFixtureDirs(): array
	{
		return [];
	}

	private function setupDatabase(Connection $db): void
	{
		$this->databaseName = 'db_tests_' . getmypid();
//		$this->dropDatabase($db);
		$this->createDatabase($db);

		$sqls = $this->getSqls();
		$fixtureDirs = $this->getFixtureDirs();

		$db->exec(sprintf('USE `%s`', $this->databaseName));
		$db->transactional(function (Connection $db) use ($sqls, $fixtureDirs) {
			$db->exec('SET foreign_key_checks = 0;');
			$db->exec('SET @disable_triggers = 1;');

			foreach ($sqls as $file) {
				Helpers::loadFromFile($db, $file);
			}

			foreach ($fixtureDirs as $dir) {
				foreach (Finder::find('*.sql')->from($dir) as $file) {
					Helpers::loadFromFile($db, $file);
				}
			}

			$db->exec('SET foreign_key_checks = 1;');
			$db->exec('SET @disable_triggers = null;');
		});

		register_shutdown_function(function () use ($db) {
			$this->dropDatabase($db);
		});
	}

	private function createDatabase(Connection $db): void
	{
		$db->exec(sprintf('CREATE DATABASE IF NOT EXISTS `%s`', $this->databaseName));
		$this->connectToDatabase($db, $this->databaseName);
	}

	private function dropDatabase(Connection $db): void
	{
		$this->connectToDatabase($db, $this->databaseName); // connect to an existing database other than $this->databaseName
		$db->exec(sprintf('DROP DATABASE IF EXISTS `%s`', $this->databaseName));
	}

	private function connectToDatabase(Connection $db, string $databaseName): void
	{
		$db->close();
		$db->__construct(
			['dbname' => $databaseName] + $db->getParams(),
			$db->getDriver(),
			$db->getConfiguration(),
			$db->getEventManager()
		);
		$db->connect();
	}

}
