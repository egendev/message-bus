<?php

use eGen\MessageBus\Bus\CommandBus;
use eGen\MessageBus\DI\MessageBusExtension;
use Nette\DI\Compiler;
use Nette\DI\Container;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';

class Command {}

class MockHandler
{

	/** @var bool */
	private $called = FALSE;

	public function handle(Command $command)
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
		'commandBus' => []
	],
	'services' => [
		'handler' => [
			'class' => MockHandler::class,
			'tags' => ['commandBus.handler'],
		]
	]
]);

eval($compiler->compile([], 'GeneratedContainer'));

$container = new GeneratedContainer();
$commandBus = $container->getByType(CommandBus::class);

$commandBus->handle(new Command());
Assert::true($container->getService('handler')->isCalled());
