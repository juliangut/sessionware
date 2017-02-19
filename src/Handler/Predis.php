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

use Predis\Client;

/**
 * Predis session handler.
 */
class Predis extends Redis
{
    /**
     * @var Client
     */
    protected $driver;

    /**
     * Predis session handler constructor.
     *
     * @param Client $driver
     */
    public function __construct(Client $driver)
    {
        $this->driver = $driver;
    }
}
