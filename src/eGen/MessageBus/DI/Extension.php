<?php

namespace eGen\MessageBus\DI;

use Nette;
use Nette\Reflection;
use Nette\DI\Statement;
use Nette\DI\ContainerBuilder;
use Nette\DI\CompilerExtension;
use eGen\MessageBus;
use SimpleBus\Message\CallableResolver;
use SimpleBus\Message\Name\ClassBasedNameResolver;
use SimpleBus\Message\Handler\DelegatesToMessageHandlerMiddleware;
use SimpleBus\Message\Handler\Resolver\NameBasedMessageHandlerResolver;
use SimpleBus\Message\CallableResolver\ServiceLocatorAwareCallableResolver;

class Extension extends CompilerExtension
{

	const TAG_HANDLER = 'bus.handler';

	/** @var array */
	public $defaults = array(
		'messageBus' => MessageBus\MessageBus::class,
		'commandNameResolver' => ClassBasedNameResolver::class,
		'handlers' => [],
		'middlewares' => [
			'before' => [],
			'after' => []
		],
		'autowire' => TRUE
	);

	/** @var array */
	private $commandMap = [];

	public function loadConfiguration()
	{
		$builder = $this->getContainerBuilder();
		$config = $this->getConfig($this->defaults);

		$builder->addDefinition($this->prefix('serviceLocator'))
				->setClass(MessageBus\ServiceLocator::class)
				->setAutowired(FALSE);

		$builder->addDefinition($this->prefix('callableResolver'))
				->setClass(ServiceLocatorAwareCallableResolver::class, [['@' . $this->prefix('serviceLocator'), 'get']])
				->setAutowired(FALSE);

		$builder->addDefinition($this->prefix('messageHandlers'))
				->setClass(CallableResolver\CallableMap::class, [$this->commandMap, '@' . $this->prefix('callableResolver')])
				->setAutowired(FALSE);

		$builder->addDefinition($this->prefix('handlerResolver'))
				->setClass(NameBasedMessageHandlerResolver::class, [new Statement($config['commandNameResolver']), '@' . $this->prefix('messageHandlers')])
				->setAutowired(FALSE);

		$builder->addDefinition($this->prefix('messageBus'))
				->setClass($config['messageBus'])
				->addSetup('appendMiddleware', [new Statement(DelegatesToMessageHandlerMiddleware::class, ['@' . $this->prefix('handlerResolver')])]);

		$this->resolveMiddlewares($config, $builder);
		$this->resolveHandlers($config, $builder);
	}


	public function beforeCompile()
	{
		$builder = $this->getContainerBuilder();

		foreach (array_keys($builder->findByTag(self::TAG_HANDLER)) as $serviceName) {
			$def = $builder->getDefinition($serviceName);
			$this->analyzeHandlerClass($def->getClass(), $serviceName);
			$def->setAutowired(isset($def->autowired) ? $def->autowired : $this->defaults['autowire']);
		}

		$def = $builder->getDefinition($this->prefix('messageHandlers'));
		$args = $def->getFactory()->arguments;
		$args[0] = $this->commandMap;
		$def->setArguments($args);
	}

	private function resolveHandlers(array $config, ContainerBuilder $builder)
	{
		Nette\Utils\Validators::assertField($config, 'handlers', 'array');
		foreach ($config['handlers'] as $handler) {
			$def = $builder->addDefinition($this->prefix('handler.' . md5(Nette\Utils\Json::encode($handler))));

			list($def->factory) = Nette\DI\Compiler::filterArguments(array(
				is_string($handler) ? new Nette\DI\Statement($handler) : $handler
			));

			list($handlerClass) = (array) $builder->normalizeEntity($def->factory->entity);
			if (class_exists($handlerClass)) {
				$def->class = $handlerClass;
			}

			$def->addTag(self::TAG_HANDLER);
		}
	}

	private function resolveMiddlewares(array $config, ContainerBuilder $builder)
	{
		$messageBus = $builder->getDefinition($this->prefix('messageBus'));

		Nette\Utils\Validators::assertField($config['middlewares'], 'before', 'array');
		foreach ($config['middlewares']['before'] as $middleware) {
			$def = $this->getMiddlewareDefinition($middleware, $builder);
			$messageBus->addSetup('prependMiddleware', [$def]);
		}

		Nette\Utils\Validators::assertField($config['middlewares'], 'after', 'array');
		foreach ($config['middlewares']['after'] as $middleware) {
			$def = $this->getMiddlewareDefinition($middleware, $builder);
			$messageBus->addSetup('appendMiddleware', [$def]);
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

	private function analyzeHandlerClass($className, $serviceName)
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
			$commandName = $parameters[0]->className;

			if(isset($this->commandMap[$commandName])) {
				throw new MessageBus\MultipleHandlersFoundException('There are multiple handlers for query ' . $commandName . '. There must be only one!');
			}

			$this->commandMap[$commandName] = "$serviceName::$method->name";
		}
	}

}
