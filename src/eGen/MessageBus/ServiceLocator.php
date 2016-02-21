<?php

namespace eGen\MessageBus;

use Nette\DI;

class ServiceLocator
{

	/** @var DI\Container */
	private $context;

	public function __construct(DI\Container $context)
	{
		$this->context = $context;
	}

	/**
	 * @param string $name
	 * @return callable
	 */
	public function get($name)
	{
		$explode = explode('::', $name);
		$service = $this->context->getService($explode[0]);

		return [$service, $explode[1]];
	}

}
