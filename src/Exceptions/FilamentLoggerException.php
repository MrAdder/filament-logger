<?php

namespace MrAdder\FilamentLogger\Exceptions;

use RuntimeException;

/**
 * Base for every exception this package throws.
 *
 * Catching this lets an application handle package failures without also
 * swallowing unrelated runtime errors.
 */
class FilamentLoggerException extends RuntimeException {}
