<?php

declare(strict_types=1);

namespace eGen\MessageBus\Bus;

use eGen\MessageBus\QueryBus\IMiddleware;
use eGen\MessageBus\QueryBus\IQueryBus;

class QueryBus implements IQueryBus
{

	/**
	 * @var IMiddleware[]
	 */
	private $middlewares = [];

	public function __construct(array $middlewares = [])
	{
		foreach ($middlewares as $middleware) {
			$this->appendMiddleware($middleware);
		}
	}

	/**
	 * Appends new middleware for this message bus. Should only be used at configuration time.
	 *
	 * @private
	 * @param IMiddleware $middleware
	 * @return void
	 */
	public function appendMiddleware(IMiddleware $middleware)
	{
		$this->middlewares[] = $middleware;
	}

	/**
	 * Returns a list of middlewares. Should only be used for introspection.
	 *
	 * @private
	 * @return IMiddleware[]
	 */
	public function getMiddlewares()
	{
		return $this->middlewares;
	}

	/**
	 * Prepends new middleware for this message bus. Should only be used at configuration time.
	 *
	 * @private
	 * @param IMiddleware $middleware
	 * @return void
	 */
	public function prependMiddleware(IMiddleware $middleware)
	{
		array_unshift($this->middlewares, $middleware);
	}

	public function handle($message)
	{
		return call_user_func($this->callableForNextMiddleware(0), $message);
	}

	private function callableForNextMiddleware($index)
	{
		if (!isset($this->middlewares[$index])) {
			return function () {
			};
		}

		$middleware = $this->middlewares[$index];

		return function ($message) use ($middleware, $index) {
			return $middleware->handle($message, $this->callableForNextMiddleware($index + 1));
		};
	}

}
