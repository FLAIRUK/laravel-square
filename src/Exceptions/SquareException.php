<?php

namespace FLAIRUK\Square\Exceptions;

use RuntimeException;

/**
 * Base class for this package's own exceptions.
 *
 * Errors returned by the Square API are thrown by the SDK as
 * \Square\Exceptions\SquareApiException (and transport errors as
 * \Square\Exceptions\SquareException); they are not wrapped.
 */
class SquareException extends RuntimeException {}
