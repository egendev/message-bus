<?php

declare(strict_types=1);

namespace eGen\MessageBus\Bus;

use SimpleBus\Message\Bus\Middleware\MessageBusSupportingMiddleware;

class CommandBus extends MessageBusSupportingMiddleware {}
