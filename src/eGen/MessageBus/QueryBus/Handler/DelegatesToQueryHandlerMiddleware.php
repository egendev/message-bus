<?php

namespace eGen\MessageBus\QueryBus\Handler;

use eGen\MessageBus\QueryBus\IMiddleware;
use SimpleBus\Message\Bus;
use SimpleBus\Message\Handler\Resolver\MessageHandlerResolver;

class DelegatesToQueryHandlerMiddleware implements IMiddleware
{

	/**
	 * @var MessageHandlerResolver
	 */
	private $resolver;

	public function __construct(MessageHandlerResolver $resolver)
	{
		$this->resolver = $resolver;
	}

	/**
	 * Handles the message by resolving the correct message handler and calling it.
	 *
	 * {@inheritdoc}
	 */
	public function handle($message, callable $next)
	{
		$handler = $this->resolver->resolve($message);

		$next($message);

		return call_user_func($handler, $message);
	}

}
