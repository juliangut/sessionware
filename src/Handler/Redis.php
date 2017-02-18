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

/**
 * Redis session handler.
 */
class Redis implements Handler
{
    use HandlerTrait;

    /**
     * @var \Redis
     */
    protected $driver;

    /**
     * Redis session handler constructor.
     *
     * @param \Redis $driver
     */
    public function __construct(\Redis $driver)
    {
        $this->driver = $driver;
    }

    /**
     * {@inheritdoc}
     *
     * @throws \RuntimeException
     */
    public function open($savePath, $sessionName)
    {
        $this->testConfiguration();

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function read($sessionId)
    {
        $this->driver->expire($sessionId, $this->configuration->getLifetime());

        return $this->driver->get($sessionId);
    }

    /**
     * {@inheritdoc}
     */
    public function write($sessionId, $sessionData)
    {
        $this->driver->set($sessionId, $sessionData);
        $this->driver->expire($sessionId, $this->configuration->getLifetime());

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function destroy($sessionId)
    {
        $this->driver->del($sessionId);

        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @SuppressWarnings(PMD.ShortMethodName)
     */
    public function gc($maxLifetime)
    {
        return true;
    }
}
