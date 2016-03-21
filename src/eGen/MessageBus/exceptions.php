<?php

namespace eGen\MessageBus;

use Exception;

class UnsupportedBusException extends Exception {}

class MultipleHandlersFoundException extends Exception {}