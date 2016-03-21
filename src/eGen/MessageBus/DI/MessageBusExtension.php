<?php

namespace eGen\MessageBus\DI;

use Nette;
use Nette\Reflection;
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
use eGen\MessageBus\MultipleHandlersFoundException;
use eGen\MessageBus\UnsupportedBusException;

class MessageBusExtension extends CompilerExtension
{

	const COMMAND_BUS = 'commandBus';
	const EVENT_BUS = 'eventBus';

	const TAG_HANDLER = 'handler';
	const TAG_SUBSCRIBER = 'subscriber';

	/** @var array */
	private $defaults = [
		self::COMMAND_BUS => [
			'bus' => Bus\CommandBus::class,
			'handlers' => [],
			'middlewares' => [
				'before' => [],
				'after' => []
			],
			'autowire' => TRUE
		],
		self::EVENT_BUS => [
			'bus' => Bus\EventBus::class,
			'subscribers' => [],
			'middlewares' => [
				'before' => [],
				'after' => []
			],
			'autowire' => TRUE
		]
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
		]
	];

	/** @var array */
	private $messages = [];

	public function loadConfiguration()
	{
		$builder = $this->getContainerBuilder();

		$builder->addDefinition($this->prefix('serviceLocator'))
			->setClass(ServiceLocator::class)
			->setAutowired(FALSE);

		$builder->addDefinition($this->prefix('callableResolver'))
			->setClass(CallableResolver\ServiceLocatorAwareCallableResolver::class, [
				['@' . $this->prefix('serviceLocator'), 'get']
			])->setAutowired(FALSE);

		$config = Helpers::merge($this->getConfig(), $this->defaults);

		if (array_key_exists(self::COMMAND_BUS, $this->getConfig())) {
			$this->configureBus($builder, $config[self::COMMAND_BUS], self::COMMAND_BUS);
		}

		if (array_key_exists(self::EVENT_BUS, $this->getConfig())) {
			$this->configureBus($builder, $config[self::EVENT_BUS], self::EVENT_BUS);
		}

		$this->config = $config;
	}

	public function beforeCompile()
	{
		$buses = [self::COMMAND_BUS, self::EVENT_BUS];
		$builder = $this->getContainerBuilder();

		foreach($buses as $bus) {
			$services = $builder->findByTag($this->getTagForResolver($bus));
			foreach (array_keys($services) as $serviceName) {
				$def = $builder->getDefinition($serviceName);
				if ($bus === self::COMMAND_BUS) {
					$this->analyzeHandlerClass($def->getClass(), $serviceName, $bus);
				} elseif($bus === self::EVENT_BUS) {
					$this->analyzeSubscriberClass($def->getClass(), $serviceName, $bus);
				}
				$def->setAutowired(FALSE);
			}

			$def = $builder->getDefinition($this->prefix($bus . '.messageHandlers'));
			$args = $def->getFactory()->arguments;
			$args[0] = isset($this->messages[$bus]) ? $this->messages[$bus] : [];
			$def->setArguments($args);
		}
	}

	private function configureBus(ContainerBuilder $builder, array $config, $bus)
	{
		$builder->addDefinition($this->prefix($bus . '.messageHandlers'))
			->setClass($this->classes[$bus]['callableResolver'], [[], '@' . $this->prefix('callableResolver')])
			->setAutowired(FALSE);

		$builder->addDefinition($this->prefix($bus . '.handlerResolver'))
			->setClass($this->classes[$bus]['messageResolver'], [
				new Statement(ClassBasedNameResolver::class),
				'@' . $this->prefix($bus . '.messageHandlers')
			])->setAutowired(FALSE);

		$def = $builder->addDefinition($this->prefix($bus));

		list($def->factory) = Nette\DI\Compiler::filterArguments(array(
			is_string($config['bus']) ? new Nette\DI\Statement($config['bus']) : $config['bus']
		));

		list($class) = (array)$builder->normalizeEntity($def->factory->entity);
		if (class_exists($class)) {
			$def->class = $class;
		}

		$def->addSetup('appendMiddleware', [new Statement(
			$this->classes[$bus]['middleware'],
			['@' . $this->prefix($bus . '.handlerResolver')]
		)])->setAutowired($config['autowire']);

		$this->configureMiddlewares($builder, $config, $bus);
		$this->configureResolvers($builder, $config, $bus);
	}

	private function configureMiddlewares(ContainerBuilder $builder, array $config, $bus)
	{
		Nette\Utils\Validators::assertField($config['middlewares'], 'before', 'array');
		Nette\Utils\Validators::assertField($config['middlewares'], 'after', 'array');

		$messageBus = $builder->getDefinition($this->prefix($bus));

		foreach ($config['middlewares']['before'] as $middleware) {
			$def = $this->getMiddlewareDefinition($builder, $middleware, $bus);
			$messageBus->addSetup('prependMiddleware', [$def]);
		}

		foreach ($config['middlewares']['after'] as $middleware) {
			$def = $this->getMiddlewareDefinition($builder, $middleware, $bus);
			$messageBus->addSetup('appendMiddleware', [$def]);
		}
	}

	private function configureResolvers(ContainerBuilder $builder, array $config, $bus)
	{
		$buses = [
			self::COMMAND_BUS => 'handlers',
			self::EVENT_BUS => 'subscribers'
		];

		if(!array_key_exists($bus, $buses)) {
			throw new UnsupportedBusException('Bus with name "' . $bus . '" is not supported by this extension.');
		}

		Nette\Utils\Validators::assertField($config, $buses[$bus], 'array');

		foreach ($config[$buses[$bus]] as $resolver) {
			$def = $builder->addDefinition($this->prefix($bus . '.' . md5(Nette\Utils\Json::encode($resolver))));

			list($def->factory) = Nette\DI\Compiler::filterArguments(array(
				is_string($resolver) ? new Nette\DI\Statement($resolver) : $resolver
			));

			list($class) = (array)$builder->normalizeEntity($def->factory->entity);
			if (class_exists($class)) {
				$def->class = $class;
			}

			$def->addTag($this->getTagForResolver($bus));
		}
	}

	private function getMiddlewareDefinition(ContainerBuilder $builder, $middleware, $bus)
	{
		$def = $builder->addDefinition($this->prefix($bus . '.middleware.' . md5(Nette\Utils\Json::encode($middleware))));

		list($def->factory) = Nette\DI\Compiler::filterArguments(array(
			is_string($middleware) ? new Nette\DI\Statement($middleware) : $middleware
		));

		list($class) = (array) $builder->normalizeEntity($def->factory->entity);
		if (class_exists($class)) {
			$def->class = $class;
		}

		$def->setAutowired(FALSE);

		return $def;
	}

	private function analyzeHandlerClass($className, $serviceName, $bus)
	{
		$ref = new Reflection\ClassType($className);

		foreach($ref->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
			if(strpos($method->getName(), '__') === 0) {
				continue;
			}

			if(count($method->getParameters()) !== 1) {
				continue;
			}

			$parameters = $method->getParameters();
			$message = $parameters[0]->className;

			if(isset($this->messages[$bus][$message])) {
				throw new MultipleHandlersFoundException(
					'There are multiple handlers for message ' . $message . '. There must be only one!'
				);
			}

			$this->messages[$bus][$message] = "$serviceName::$method->name";
		}
	}

	private function analyzeSubscriberClass($className, $serviceName, $bus)
	{
		$ref = new Reflection\ClassType($className);

		foreach($ref->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
			if(strpos($method->getName(), '__') === 0) {
				continue;
			}

			if(count($method->getParameters()) !== 1) {
				continue;
			}

			$parameters = $method->getParameters();
			$message = $parameters[0]->className;

			$this->messages[$bus][$message][] = "$serviceName::$method->name";
		}
	}

	private function getTagForResolver($bus)
	{
		$buses = [
			self::COMMAND_BUS => self::TAG_HANDLER,
			self::EVENT_BUS => self::TAG_SUBSCRIBER
		];

		if(!array_key_exists($bus, $buses)) {
			throw new UnsupportedBusException('Bus with name "' . $bus . '" is not supported by this extension.');
		}

		return $bus . '.' . $buses[$bus];
	}

}