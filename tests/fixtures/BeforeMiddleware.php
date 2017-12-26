<?php

namespace Fixtures;

use SimpleBus\Message\Bus\Middleware\MessageBusMiddleware;

class BeforeMiddleware implements MessageBusMiddleware
{

	public function handle($message, callable $next) {}

}
