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
 * Memcached session handler.
 */
class Memcached implements Handler
{
    use HandlerTrait;

    /**
     * @var \Memcached
     */
    protected $driver;

    /**
     * Memcached session handler constructor.
     *
     * @param \Memcached $driver
     */
    public function __construct(\Memcached $driver)
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
        return $this->driver->get($sessionId) ?: '';
    }

    /**
     * {@inheritdoc}
     */
    public function write($sessionId, $sessionData)
    {
        return $this->driver->set($sessionId, $sessionData, time() + $this->configuration->getLifetime());
    }

    /**
     * {@inheritdoc}
     */
    public function destroy($sessionId)
    {
        return $this->driver->delete($sessionId);
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
