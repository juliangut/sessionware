<?php

/*
 * sessionware (https://github.com/juliangut/sessionware).
 * PSR7 session management middleware.
 *
 * @license BSD-3-Clause
 * @link https://github.com/juliangut/sessionware
 * @author Julián Gutiérrez <juliangut@gmail.com>
 */

declare(strict_types=1);

namespace Jgut\Sessionware\Handler;

use Jgut\Sessionware\Configuration;

/**
 * Session handler interface.
 */
interface Handler extends \SessionHandlerInterface
{
    /**
     * Set configuration.
     *
     * @param Configuration $configuration
     *
     * @return static
     */
    public function setConfiguration(Configuration $configuration);
}
