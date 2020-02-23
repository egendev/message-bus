<?php

namespace eGen\MessageBus\DI;

use eGen\MessageBus\Bus\CommandBus;
use eGen\MessageBus\Bus\EventBus;
use eGen\MessageBus\Bus\QueryBus;
use Fixtures\AfterMiddleware;
use Fixtures\BeforeMiddleware;
use Fixtures\Command;
use Fixtures\CommandHandler;
use Fixtures\Event;
use Fixtures\Query;
use Fixtures\QueryHandler;
use Nette\Configurator;
use Nette\DI\Container;
use PHPUnit\Framework\TestCase;
use SimpleBus\Message\Bus\Middleware\MessageBusSupportingMiddleware;
use SimpleBus\Message\Handler\DelegatesToMessageHandlerMiddleware;

class MessageBusExtensionTest extends TestCase
{

	public function testMiddlewares()
	{
		$container = $this->getContainer(__DIR__ . '/middlewares.neon');

		$bus = $container->getByType(MessageBusSupportingMiddleware::class);
		$middlewares = $bus->getMiddlewares();

		$this->assertCount(5, $middlewares);

		$expectedMiddlewares = [
			BeforeMiddleware::class,
			BeforeMiddleware::class,
			DelegatesToMessageHandlerMiddleware::class,
			AfterMiddleware::class,
			AfterMiddleware::class,
		];

		foreach ($expectedMiddlewares as $index => $expectedMiddlewareClass) {
			$this->assertInstanceOf($expectedMiddlewareClass, $middlewares[$index]);
		}
	}

	public function testCommandBusPassesCommandToHandler()
	{
		$container = $this->getContainer(__DIR__ . '/commandBus.neon');
		$bus = $container->getByType(CommandBus::class);

		$bus->handle(new Command());

		$handler = $container->getByType(CommandHandler::class);

		$this->assertTrue($handler->isCalled());
	}

	public function testEventBusPassesEventToAllRegisteredSubscribers()
	{
		$container = $this->getContainer(__DIR__ . '/eventBus.neon');
		$eventBus = $container->getByType(EventBus::class);

		$eventBus->handle(new Event());

		$this->assertTrue($container->getService('subscriber1')->isCalled());
		$this->assertTrue($container->getService('subscriber2')->isCalled());

	}

	public function testQueryBusReturnsCorrectResultFromRegisteredHandler()
	{
		$container = $this->getContainer(__DIR__ . '/queryBus.neon');
		$bus = $container->getByType(QueryBus::class);

		$result = $bus->handle(new Query());

		$handler = $container->getByType(QueryHandler::class);

		$this->assertSame(QueryHandler::RESULT, $result);
		$this->assertTrue($handler->isCalled());
	}

	private function getContainer(string $configFile): Container
	{
		$configurator = new Configurator();
		$configurator->setTempDirectory(__DIR__ . '/../temp');

		$configurator->addConfig(__DIR__ . '/base.neon');
		$configurator->addConfig($configFile);

		return $configurator->createContainer();
	}

}
