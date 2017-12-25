<?php

use eGen\MessageBus\Bus\QueryBus;
use eGen\MessageBus\DI\MessageBusExtension;
use Nette\DI\Compiler;
use Nette\DI\Container;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';

class Query {}

class MockHandler
{

	/** @var bool */
	private $called = FALSE;

	public function handle(Query $query)
	{
		$this->called = TRUE;
		return 'query-result';
	}

	public function isCalled()
	{
		return $this->called;
	}

}

$compiler = new Compiler();

$compiler->addExtension('messageBus', new MessageBusExtension());

$compiler->addConfig([
	'messageBus' => [
		'queryBus' => []
	],
	'services' => [
		'handler' => [
			'class' => MockHandler::class,
			'tags' => ['queryBus.handler'],
		]
	]
]);

$compiler->setClassName('GeneratedContainer');
eval($compiler->compile());

$container = new GeneratedContainer();
$queryBus = $container->getByType(QueryBus::class);

Assert::same('query-result', $queryBus->handle(new Query()));
Assert::true($container->getService('handler')->isCalled());
