<?php

namespace bitpvp\replay\utils;

use Throwable;

/**
 * Represents an exception when the server does
 * an action that is not supposed to be while
 * recording, this should be avoided by the developer
 */
class IlegalHandlingException extends \RuntimeException {


    static public function create(string $message, ?Throwable $previous = null): self {
        return new self("Ilegal Server Handling Exception: $message", 0, $previous);
    }
}