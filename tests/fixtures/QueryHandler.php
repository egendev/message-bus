<?php

declare(strict_types=1);

namespace Fixtures;

class QueryHandler
{

	const RESULT = 'query-result';

	/** @var bool */
	private $called = FALSE;

	public function handle(Query $query)
	{
		$this->called = TRUE;
		return self::RESULT;
	}

	public function isCalled(): bool
	{
		return $this->called;
	}

}
