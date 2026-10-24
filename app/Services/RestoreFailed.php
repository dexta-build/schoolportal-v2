<?php

declare(strict_types=1);

namespace App\Services;

/**
 * A deleted item could not be restored; the message is shown to the user as is.
 */
class RestoreFailed extends \RuntimeException {}
