<?php

namespace Fixtures;

use SimpleBus\Message\Bus\Middleware\MessageBusMiddleware;

class BeforeMiddleware implements MessageBusMiddleware
{


	public function __construct(bool $foo)
	{
	}

	public function handle($message, callable $next) {}

}
