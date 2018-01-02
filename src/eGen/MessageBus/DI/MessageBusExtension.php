<?php

namespace eGen\MessageBus\DI;

use Nette\DI\Statement;
use eGen\MessageBus\Bus;
use Nette\DI\Config\Helpers;
use Nette\DI\ContainerBuilder;
use Nette\DI\CompilerExtension;
use SimpleBus\Message\Handler;
use SimpleBus\Message\Subscriber;
use eGen\MessageBus\ServiceLocator;
use SimpleBus\Message\CallableResolver;
use SimpleBus\Message\Name\ClassBasedNameResolver;
use eGen\MessageBus\Exceptions\MultipleHandlersFoundException;
use eGen\MessageBus\QueryBus\Handler\DelegatesToQueryHandlerMiddleware;

class MessageBusExtension extends CompilerExtension
{

	const COMMAND_BUS = 'commandBus';
	const EVENT_BUS = 'eventBus';
	const QUERY_BUS = 'queryBus';

	const TAG_HANDLER = 'handler';
	const TAG_SUBSCRIBER = 'subscriber';

	/** @var array */
	private $defaults = [
		self::COMMAND_BUS => [
			'bus' => Bus\CommandBus::class,
			'middlewares' => [
				'before' => [],
				'after' => []
			],
			'autowire' => TRUE,
			'tag' => 'commandBus.handler',
		],
		self::EVENT_BUS => [
			'bus' => Bus\EventBus::class,
			'middlewares' => [
				'before' => [],
				'after' => []
			],
			'autowire' => TRUE,
			'tag' => 'eventBus.subscriber'
		],
		self::QUERY_BUS => [
			'bus' => Bus\QueryBus::class,
			'middlewares' => [
				'before' => [],
				'after' => []
			],
			'autowire' => TRUE,
			'tag' => 'queryBus.handler'
		],
	];

	private $classes = [
		self::COMMAND_BUS => [
			'callableResolver' => CallableResolver\CallableMap::class,
			'messageResolver' => Handler\Resolver\NameBasedMessageHandlerResolver::class,
			'middleware' => Handler\DelegatesToMessageHandlerMiddleware::class
		],
		self::EVENT_BUS => [
			'callableResolver' => CallableResolver\CallableCollection::class,
			'messageResolver' => Subscriber\Resolver\NameBasedMessageSubscriberResolver::class,
			'middleware' => Subscriber\NotifiesMessageSubscribersMiddleware::class
		],
		self::QUERY_BUS => [
			'callableResolver' => CallableResolver\CallableMap::class,
			'messageResolver' => Handler\Resolver\NameBasedMessageHandlerResolver::class,
			'middleware' => DelegatesToQueryHandlerMiddleware::class,
		],
	];

	/** @var array<string, array<string>> */
	private $messages = [];

	public function loadConfiguration()
	{
		$builder = $this->getContainerBuilder();

		$builder->addDefinition($this->prefix('serviceLocator'))
			->setFactory(ServiceLocator::class)
			->setAutowired(FALSE);

		$builder->addDefinition($this->prefix('callableResolver'))
			->setFactory(CallableResolver\ServiceLocatorAwareCallableResolver::class, [
				['@' . $this->prefix('serviceLocator'), 'get']
			])->setAutowired(FALSE);

		$config = $this->getConfig();

		$builder->addDefinition($this->prefix('messageNameResolver'))
			->setFactory(ClassBasedNameResolver::class);

		foreach([self::COMMAND_BUS, self::EVENT_BUS, self::QUERY_BUS] as $bus) {
			if (array_key_exists($bus, $config)) {
				$config[$bus] = Helpers::merge($config[$bus], $this->defaults[$bus]);
				$this->configureBus($builder, $config[$bus], $bus);
			}
		}

		$this->config = $config;
	}

	public function beforeCompile()
	{
		$buses = array_keys($this->config);
		$builder = $this->getContainerBuilder();

		foreach($buses as $bus) {
			$services = $builder->findByTag($this->getTagForResolver($bus));
			foreach (array_keys($services) as $serviceName) {
				$def = $builder->getDefinition($serviceName);

				if ($bus === self::COMMAND_BUS || $bus === self::QUERY_BUS) {
					$this->analyzeHandlerClass($def->getType(), $serviceName, $bus);
				} elseif($bus === self::EVENT_BUS) {
					$this->analyzeSubscriberClass($def->getType(), $serviceName, $bus);
				}
				$def->setAutowired(FALSE);
			}

			$def = $builder->getDefinition($this->prefix($bus . '.messageHandlers'));
			$args = $def->getFactory()->arguments;
			$args[0] = $this->messages[$bus] ?? [];
			$def->setArguments($args);
		}
	}

	private function configureBus(ContainerBuilder $builder, array $config, $bus)
	{
		$handlersMap = $builder->addDefinition($this->prefix($bus . '.messageHandlers'))
			->setFactory($this->classes[$bus]['callableResolver'], [[], '@' . $this->prefix('callableResolver')])
			->setAutowired(FALSE);

		$builder->addDefinition($this->prefix($bus . '.handlerResolver'))
			->setFactory($this->classes[$bus]['messageResolver'], [
				'@' . $this->prefix('messageNameResolver'),
				$handlersMap
			])
			->setAutowired(FALSE);

		$builder->addDefinition($this->prefix($bus))
			->setFactory($config['bus']);

		$this->configureMiddlewares($builder, $config, $bus);
	}

	private function configureMiddlewares(ContainerBuilder $builder, array $config, string $busName)
	{
		$messageBus = $builder->getDefinition($this->prefix($busName));

		$delegatingMiddleware = new Statement(
			$this->classes[$busName]['middleware'],
			['@' . $this->prefix($busName . '.handlerResolver')]
		);

		$middlewares = array_merge(
			$config['middlewares']['before'],
			[$delegatingMiddleware],
			$config['middlewares']['after']
		);

		foreach($middlewares as $index => $middleware) {
			if(is_string($middleware)) {
				$middleware = $builder
					->addDefinition($this->prefix($busName . '.middleware' . $index))
					->setFactory($middleware)
					->setAutowired(FALSE);
			}

			$messageBus->addSetup('appendMiddleware', [$middleware]);
		}
	}

	private function analyzeHandlerClass(string $className, string $serviceName, string $busName)
	{
		$handleMethods = $this->getAllHandleMethods($className, $serviceName);

		foreach ($handleMethods as $method => $messageName) {
			if (isset($this->messages[$busName][$messageName])) {
				throw new MultipleHandlersFoundException(
					'There are multiple handlers for message ' . $messageName . '. There must be only one!'
				);
			}

			$this->messages[$busName][$messageName] = $method;
		}
	}

	private function analyzeSubscriberClass(string $className, string $serviceName, string $busName)
	{
		$handleMethods = $this->getAllHandleMethods($className, $serviceName);

		foreach ($handleMethods as $method => $messageName) {
			$this->messages[$busName][$messageName][] = $method;
		}
	}

	private function getAllHandleMethods(string $className, string $serviceName): array
	{
		$ref = new \ReflectionClass($className);

		$handlers = [];

		foreach ($ref->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
			if (strpos($method->getName(), '__') === 0 && $method->getName() !== '__invoke') {
				continue;
			}

			if (count($method->getParameters()) !== 1) {
				continue;
			}

			$parameters = $method->getParameters();
			$message = $parameters[0]->getType()->getName();

			$handlers["$serviceName::$method->name"] = $message;
		}

		return $handlers;
	}

	private function getTagForResolver(string $bus): string
	{
		return $this->config[$bus]['tag'];
	}

}
