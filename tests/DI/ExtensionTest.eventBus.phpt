<?php

use eGen\MessageBus\Bus\EventBus;
use eGen\MessageBus\DI\MessageBusExtension;
use Nette\DI\Compiler;
use Nette\DI\Container;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';

class Event {}

class MockSubscriber
{

	/** @var bool */
	private $called = FALSE;

	public function handle(Event $event)
	{
		$this->called = TRUE;
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
		'eventBus' => []
	],
	'services' => [
		'subscriber1' => [
			'class' => MockSubscriber::class,
			'tags' => ['eventBus.subscriber'],
		],
		'subscriber2' => [
			'class' => MockSubscriber::class,
			'tags' => ['eventBus.subscriber'],
		]
	]
]);

eval($compiler->compile([], 'GeneratedContainer'));

$container = new GeneratedContainer();
$eventBus = $container->getByType(EventBus::class);

$eventBus->handle(new Event());
Assert::true($container->getService('subscriber1')->isCalled());
Assert::true($container->getService('subscriber2')->isCalled());
