<?php

namespace eGen\MessageBus\DI;

use Fixtures\AfterMiddleware;
use Fixtures\BeforeMiddleware;
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

	private function getContainer(string $configFile): Container
	{
		$configurator = new Configurator();
		$configurator->setTempDirectory(__DIR__ . '/../temp');
		$configurator->setDebugMode(true);

		$robotLoader = $configurator->createRobotLoader();
		$robotLoader->addDirectory(__DIR__ . '/../fixtures');
		$robotLoader->register();

		$configurator->addConfig($configFile);

		return $configurator->createContainer();
	}

}
