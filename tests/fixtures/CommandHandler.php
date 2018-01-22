<?php

declare(strict_types=1);

namespace Fixtures;

class CommandHandler
{

	/** @var bool */
	private $called = FALSE;

	public function handle(Command $command)
	{
		$this->called = TRUE;
	}

	public function isCalled(): bool
	{
		return $this->called;
	}

}
