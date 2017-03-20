<?php declare(strict_types = 1);

namespace JedenWeb\TesterUtils;

use Kdyby\Doctrine\Connection;
use Nette\SmartObject;

/**
 * @method void onConnect(Connection $connection)
 * @author Pavel Jurásek
 */
class ConnectionMock extends Connection
{

	use SmartObject;

	/** @var callable[] */
	public $onConnect = [];

	public function connect(): void
	{
		$result = parent::connect();

		if ($result) {
			$this->onConnect($this);
		}
	}

}
