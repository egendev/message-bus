<?php

declare(strict_types=1);

namespace eGen\MessageBus\QueryBus;

interface IQueryBus
{

	/**
	 * @param object $query
	 * @return mixed
	 */
	public function handle($query);

}
