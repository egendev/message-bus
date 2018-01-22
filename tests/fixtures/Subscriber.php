<?php

declare(strict_types=1);

namespace Fixtures;

class Subscriber
{

	/** @var bool */
	private $called = FALSE;

	public function handle(Event $event)
	{
		$this->called = TRUE;
	}

	public function isCalled(): bool
	{
		return $this->called;
	}

}
