<?php declare(strict_types = 1);

namespace JedenWeb\TesterUtils;

use Nette;
use Nette\Http\Request as HttpRequest;

/**
 * @author Filip Procházka <filip@prochazka.su>
 */
trait PresenterRunner
{

	/** @var Nette\Application\UI\Presenter */
	protected $presenter;

	/** @var  Nette\Http\UrlScript */
	private $fakeUrl;

	protected function openPresenter(string $fqa): void
	{
		/** @var CompiledContainer|PresenterRunner $this */

		$sl = $this->getContainer();

		//insert fake HTTP Request for Presenter - for presenter->link() etc.
		$params = $sl->getParameters();
		$this->fakeUrl = new Nette\Http\UrlScript(isset($params['console']['url']) ? $params['console']['url'] : 'localhost');

		$sl->removeService('httpRequest');
		$sl->addService('httpRequest', new HttpRequest($this->fakeUrl, null, [], [], [], [], PHP_SAPI, '127.0.0.1', '127.0.0.1'));

		/** @var Nette\Application\IPresenterFactory $presenterFactory */
		$presenterFactory = $sl->getByType('Nette\Application\IPresenterFactory');

		$name = substr($fqa, 0, $namePos = strrpos($fqa, ':'));
		$class = $presenterFactory->getPresenterClass($name);

		$overriddenPresenter = 'Tests\\' . $class;

		if (!class_exists($overriddenPresenter)) {
			$classPos = strrpos($class, '\\');
			eval('namespace Tests\\' . substr($class, 0, $classPos) . '; class ' . substr($class, $classPos + 1) . ' extends \\' . $class . ' { '
				. 'protected function startup() { if ($this->getParameter("__terminate") == true) { $this->terminate(); } parent::startup(); } '
				. 'public static function getReflection() { return new \Nette\Application\UI\ComponentReflection(parent::getReflection()->getParentClass()); } '
				. '}');
		}

		$this->presenter = $sl->createInstance($overriddenPresenter);
		$sl->callInjects($this->presenter);

		$app = $this->getService('Nette\Application\Application');
		$appRefl = new \ReflectionProperty($app, 'presenter');
		$appRefl->setAccessible(true);
		$appRefl->setValue($app, $this->presenter);

		$this->presenter->autoCanonicalize = true;
		$this->presenter->run(new Nette\Application\Request($name, 'GET', ['action' => substr($fqa, $namePos + 1) ?: 'default', '__terminate' => true]));
	}

	/**
	 * @param string $action
	 * @param string $method
	 * @param mixed[] $params
	 * @param mixed[] $post
	 */
	protected function runPresenterAction(string $action, string $method = 'GET', array $params = [], array $post = []): Nette\Application\IResponse
	{
		/** @var PresenterRunner $this */

		if (!$this->presenter) {
			throw new \LogicException('You have to open the presenter using $this->openPresenter($name); before calling actions');
		}

		$request = new Nette\Application\Request($this->presenter->getName(), $method, ['action' => $action] + $params, $post);

		return $this->presenter->run($request);
	}

	/**
	 * @param string $action
	 * @param string $signal
	 * @param mixed[] $params
	 * @param mixed[] $post
	 */
	protected function runPresenterSignal(string $action, string $signal, array $params = [], array $post = []): Nette\Application\IResponse
	{
		/** @var PresenterRunner $this */

		return $this->runPresenterAction($action, $post ? 'POST' : 'GET', ['do' => $signal] + $params, $post);
	}

}
