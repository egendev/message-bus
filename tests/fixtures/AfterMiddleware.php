<?php

namespace Fixtures;

use SimpleBus\Message\Bus\Middleware\MessageBusMiddleware;

class AfterMiddleware implements MessageBusMiddleware
{

	public function handle($message, callable $next)
	{
	}

}
