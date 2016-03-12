<?php

namespace eGen\MessageBus\DI;

use Nette;
use Nette\Reflection;
use Nette\DI\Statement;
use Nette\DI\Config\Helpers;
use Nette\DI\ContainerBuilder;
use Nette\DI\CompilerExtension;
use eGen\MessageBus\ServiceLocator;
use SimpleBus\Message\CallableResolver;
use SimpleBus\Message\Name\ClassBasedNameResolver;
use eGen\MessageBus\MultipleHandlersFoundException;
use SimpleBus\Message\Handler\DelegatesToMessageHandlerMiddleware;
use SimpleBus\Message\Handler\Resolver\NameBasedMessageHandlerResolver;
use SimpleBus\Message\CallableResolver\ServiceLocatorAwareCallableResolver;
use SimpleBus\Message\Subscriber\NotifiesMessageSubscribersMiddleware;
use SimpleBus\Message\Subscriber\Resolver\NameBasedMessageSubscriberResolver;

class MessageBusExtension extends CompilerExtension
{

	const TAG_HANDLER = 'handler';

	const TAG_SUBSCRIBER = 'subscriber';

	const COMMANDS = 'commands';

	const EVENTS = 'events';

	/** @var array */
	private $defaults = [
		'buses' => []
	];

	/** @var array */
	private $busDefaults = [
		'class' => NULL,
		'resolves' => self::COMMANDS,
		'handlers' => [],
		'subscribers' => [],
		'middlewares' => [
			'before' => [],
			'after' => []
		],
		'autowire' => TRUE
	];

	private $classes = [
		self::COMMANDS => [
			'callableResolver' => CallableResolver\CallableMap::class,
			'messageResolver' => NameBasedMessageHandlerResolver::class,
			'middleware' => DelegatesToMessageHandlerMiddleware::class
		],
		self::EVENTS => [
			'callableResolver' => CallableResolver\CallableCollection::class,
			'messageResolver' => NameBasedMessageSubscriberResolver::class,
			'middleware' => NotifiesMessageSubscribersMiddleware::class
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
			->setClass(ServiceLocatorAwareCallableResolver::class, [['@' . $this->prefix('serviceLocator'), 'get']])
			->setAutowired(FALSE);

		$config = Helpers::merge($this->getConfig(), $this->defaults);

		foreach($config['buses'] as $key => &$bus) {
			$bus = Helpers::merge($bus, $this->busDefaults);
			$this->validateBusConfig($bus);

			$builder->addDefinition($this->prefix($key . '.messageHandlers'))
				->setClass($this->classes[$bus['resolves']]['callableResolver'], [[], '@' . $this->prefix('callableResolver')])
				->setAutowired(FALSE);

			$builder->addDefinition($this->prefix($key . '.handlerResolver'))
				->setClass($this->classes[$bus['resolves']]['messageResolver'], [
					new Statement(ClassBasedNameResolver::class),
					'@' . $this->prefix($key . '.messageHandlers')
				])->setAutowired(FALSE);

			$def = $builder->addDefinition($this->prefix($key . '.bus'));

			list($def->factory) = Nette\DI\Compiler::filterArguments(array(
				is_string($bus['class']) ? new Nette\DI\Statement($bus['class']) : $bus['class']
			));

			list($class) = (array) $builder->normalizeEntity($def->factory->entity);
			if (class_exists($class)) {
				$def->class = $class;
			}

			$def->addSetup('appendMiddleware', [new Statement(
				$this->classes[$bus['resolves']]['middleware'],
				['@' . $this->prefix($key . '.handlerResolver')]
			)])->setAutowired($bus['autowire']);
		}

		$this->configureMiddlewares($config, $builder);
		$this->configureResolvers($config, $builder);
		$this->config = $config;
	}


	public function beforeCompile()
	{
		$builder = $this->getContainerBuilder();

		foreach($this->config['buses'] as $key => $bus) {
			$services = $builder->findByTag($this->getTagForBus($key, $bus['resolves']));
			foreach (array_keys($services) as $serviceName) {
				$def = $builder->getDefinition($serviceName);
				if($bus['resolves'] == self::EVENTS) {
					$this->analyzeSubscriberClass($def->getClass(), $serviceName, $key);
				} else {
					$this->analyzeHandlerClass($def->getClass(), $serviceName, $key);
				}
				$def->setAutowired(FALSE);
			}

			$def = $builder->getDefinition($this->prefix($key . '.messageHandlers'));
			$args = $def->getFactory()->arguments;
			$args[0] = isset($this->messages[$key]) ? $this->messages[$key]: [];
			$def->setArguments($args);
		}
	}

	private function validateBusConfig($config)
	{
		$this->validateConfig($this->busDefaults, $config);

		if($config['resolves'] == self::EVENTS) {
			if(count($config['handlers'])) {
				throw new Nette\Utils\AssertionException('Bus resolving "' . self::EVENTS . '" cannot contain field named handlers.');
			}
			return TRUE;
		} elseif($config['resolves'] == self::COMMANDS) {
			if(count($config['subscribers'])) {
				throw new Nette\Utils\AssertionException('Bus resolving "' . self::COMMANDS . '" cannot contain field named subscribers.');
			}
			return TRUE;
		}

		throw new Nette\Utils\AssertionException('Unknown value named "' . $config['resolves'] . '" in bus config.');
	}

	private function configureMiddlewares(array $config, ContainerBuilder $builder)
	{
		foreach($config['buses'] as $key => $bus) {
			$messageBus = $builder->getDefinition($this->prefix($key . '.bus'));

			Nette\Utils\Validators::assertField($bus['middlewares'], 'before', 'array');
			foreach ($bus['middlewares']['before'] as $middleware) {
				$def = $this->getMiddlewareDefinition($middleware, $builder);
				$messageBus->addSetup('prependMiddleware', [$def]);
			}

			Nette\Utils\Validators::assertField($bus['middlewares'], 'after', 'array');
			foreach ($bus['middlewares']['after'] as $middleware) {
				$def = $this->getMiddlewareDefinition($middleware, $builder);
				$messageBus->addSetup('appendMiddleware', [$def]);
			}
		}
	}

	private function configureResolvers(array $config, ContainerBuilder $builder)
	{
		foreach($config['buses'] as $key => $bus) {
			$type = $bus['resolves'] == self::EVENTS ? 'subscribers' : 'handlers';
			Nette\Utils\Validators::assertField($bus, $type, 'array');
			foreach ($bus[$type] as $resolver) {
				$def = $builder->addDefinition($this->prefix($key . '.' . md5(Nette\Utils\Json::encode($resolver))));

				list($def->factory) = Nette\DI\Compiler::filterArguments(array(
					is_string($resolver) ? new Nette\DI\Statement($resolver) : $resolver
				));

				list($class) = (array) $builder->normalizeEntity($def->factory->entity);
				if (class_exists($class)) {
					$def->class = $class;
				}

				$def->addTag($this->getTagForBus($key, $bus['resolves']));
			}
		}
	}

	private function getMiddlewareDefinition($middleware, ContainerBuilder $builder)
	{
		$def = $builder->addDefinition($this->prefix('middleware.' . md5(Nette\Utils\Json::encode($middleware))));

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

	private function getTagForBus($bus, $resolves)
	{
		if($resolves == self::COMMANDS) {
			return $bus . '.' . self::TAG_HANDLER;
		} elseif($resolves == self::EVENTS) {
			return $bus . '.' . self::TAG_SUBSCRIBER;
		}

		throw new Nette\Utils\AssertionException('Unknown value named "' . $resolves . '" in bus config.');
	}

}