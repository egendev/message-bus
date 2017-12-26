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

	public function get(string $name): callable
	{
		$explode = explode('::', $name);
		$service = $this->context->getService($explode[0]);

		return [$service, $explode[1]];
	}

}
