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

### CommandBus configuration

If you want to use CommandBus in your application you just need to add these two lines to your config.neon file.

```yml
messageBus:
	commandBus:
```
This will add new service `eGen\MessageBus\Bus\CommandBus` into your DI container. You can use your own CommandBus class without any problems. Your class must implement interface `SimpleBus\Message\Bus\MessageBus` and than you will be able to replace default `commandBus` class in configuration section.
```yml
services:
    commandBus: App\YourCommandBus
messageBus:
	commandBus:
	    bus: @commandBus
```
Now you will need some handlers for resolving your commands. These handlers should be registered in your config.neon too. The most flexible and recommended way to add some handler is shown in example below.

```yml
services:
    seo.handler:
        class: App\Model\Handlers\SeoHandler
        tags: [commandBus.handler]
```
If you tag the service with `commandBus.handler` tag, it will be automatically registered to your CommandBus.
There are no specific requirements for your handler class. It's very similar with your command objects. You don't need to implement any interface or inherit from another class.

### CommandBus usage

We have already fully configured the CommandBus, we can just start creating a new command object and let the command bus handle it. Anywhere you want to send some command you just need to inject `eGen\MessageBus\Bus\CommandBus` service and handle your command very simply.

```php
<?php
class YourPresenter {

    /** @var \eGen\MessageBus\Bus\CommandBus */
    private $commandBus;

    public function renderDefault() {
        $this->commandBus->handle(new Commands\TurnOnSeo());
    }
}
```


### EventBus configuration

Configuration and usage of EventBus is very similar to CommandBus.

```yml
messageBus:
	eventBus:
```

Default EventBus service `eGen\MessageBus\Bus\EventBus` will be added into your DI container.

EventBus resolves Events, so you will need some subscriber to resolve your event.

```yml
services:
    seo.subscriber:
        class: App\Model\Subscribers\SeoListener
        tags: [eventBus.subscriber]
```

### EventBus usage
Somewhere in your app...
```php
<?php
class YourPresenter {

    /** @var \eGen\MessageBus\Bus\EventBus */
    private $eventBus;

    public function renderDefault() {
        $this->eventBus->handle(new Events\SeoWasTurnedOn());
    }
}
```

## Middlewares
Each bus should have own middlewares, which will be executed before/after message is being handled.

```yml
messageBus:
	commandBus:
		middlewares:
			before:
				- Middleware\FirstMiddleware
				- @messageBus.secondMiddleware
			after:
				- Middleware\LastMiddleware

services:
    messageBus.secondMiddleware: Middleware\SecondMiddleware
```
