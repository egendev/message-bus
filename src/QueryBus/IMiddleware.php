<?php

declare(strict_types=1);

namespace eGen\MessageBus\QueryBus;

interface IMiddleware
{

	/**
	 * The provided $next callable should be called whenever the next middleware should start handling the message.
	 * Its only argument should be a Message object (usually the same as the originally provided message).
	 * It should always return (possibly modified) result of next middleware
	 *
	 * @param object $message
	 * @param callable $next
	 * @return mixed
	 */
	public function handle($message, callable $next);

}
