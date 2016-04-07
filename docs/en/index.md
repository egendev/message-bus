# eGen/MessageBus

[SimpleBus/MessageBus](https://github.com/SimpleBus/MessageBus) integration into Nette Framework.
For more information and examples check [SimpleBus/MessageBus](http://simplebus.github.io/MessageBus/doc/command_bus.html) page.

## Installation

The best way to install eGen/MessageBus extension is using  [Composer](http://getcomposer.org/):

```sh
$ composer require egen/message-bus
```

And then you should add the extension in your config.neon file.

```yml
extensions:
    messagebus: eGen\MessageBus\DI\MessageBusExtension
```

## Configuration and Usage

This extension allows you to use CommandBus/EventBus in your application.
The whole extension is ready to use without any advanced configuration.

### Command bus

If you want to use CommandBus in your application you just need to add these two lines to your config.neon file.

```yml
messageBus:
    commandBus:
```
This will add new service `eGen\MessageBus\Bus\CommandBus` into your DI container. You can use your own CommandBus class without any problems. Your class must implement interface `SimpleBus\Message\Bus\MessageBus` and than you will be able to replace default `commandBus` class in configuration section.
```yml
messageBus:
    commandBus:
```
Now you will need some handlers to resolve your commands. These handlers should be registered in your config.neon too. The most flexible and recommended way to add a handler is shown in example below.

```yml
services:
    - class: App\Model\Handlers\SeoHandler
      tags: [commandBus.handler]
```
If you tag the service as `commandBus.handler`, it will be automatically registered to your CommandBus.
Handler class doesn't have to implement any interface or inherit from another class, it's just
a class with public **public method(s) with type-hinted arguments**.

```php
<?php

namespace App\Model\Handlers;

use App\Commands;

class SeoHandler
{

    public function someHandleMethod(TurnOnSeo $command)
    {
        // Handle command
    }

}
```

Simplest command can look like this:
```php
<?php

namespace App\Commands;

class TurnOnSeo {}
```

Now you only need to create command instance.

```php
<?php

use eGen\MessageBus\Bus\CommandBus;
use App\Commands\TurnOnSeo;

class SeoPresenter extends BasePresenter
{

    /** @var CommandBus */
    private $commandBus;

    public function __construct(CommandBus $commandBus)
    {
        $this->commandBus = $commandBus;
    }


    public function handleTurnOnSeo()
    {
        $this->commandBus->handle(new TurnOnSeo());
    }

}
```


### Event bus

Configuration and usage of EventBus is very similar to CommandBus.

```yml
messageBus:
	eventBus:
```

`eGen\MessageBus\Bus\EventBus` will be added into your DI container.

EventBus resolves events, so you will need some subscriber to resolve your event.

```yml
services:
    seo.subscriber:
        class: App\Model\Subscribers\SeoMailListener
        tags: [eventBus.subscriber]
```

Event subscriber is very similiar to command handler. Only class with public method(s).

```php
<?php

namespace App\Model\Subscribers;

use App\Events\SeoWasTurnedOn;

class SeoMailListener
{

    public function handle(SeoWasTurnedOn $event)
    {
        // Send mail to admin?
    }

}

```

Difference between command and event bus is intent. There can be only one
handler fo each command. For event, you can have unlimited amount of subscribers.

You can raise event anywhere, all you need to do is pass it to EventBus.

```php
<?php

use eGen\MessageBus\Bus\EventBus;
use App\Events\SeoWasTurnedOn;

class SeoService
{

    /** @var EventBus */
    private $eventBus;

    public function turnOn()
    {
        // You just turned on SEO! Lets make everyone know
        $this->eventBus->handle(new SeoWasTurnedOn());
    }

}
```

## Middlewares
What if you wan't to wrap command handling in DB transaction, process it asynchronously, or create event log?
MessageBus comes with handy pattern called [middlewares](http://simplebus.github.io/MessageBus/doc/command_bus.html#implementing-your-own-command-bus-middleware).

There are several pre-made middlewares (logging, Doctrine transactions, ...)

Each bus have separate middlewares, which will be executed before/after message is being handled.
As middleware you can pass either name of class or already created service.

```yml
messageBus:
    commandBus:
        middlewares:
            before:
                - Middleware\FirstMiddleware
                - @instantiatedMiddleware
            after:
                - Middleware\LastMiddleware

services:
    instantiatedMiddleware: Middleware\SecondMiddleware
```
