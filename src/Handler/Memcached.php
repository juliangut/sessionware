<?php

/*
 * sessionware (https://github.com/juliangut/sessionware).
 * PSR7 compatible session management.
 *
 * @license BSD-3-Clause
 * @link https://github.com/juliangut/sessionware
 * @author Julián Gutiérrez <juliangut@gmail.com>
 */

declare(strict_types=1);

namespace Jgut\Sessionware\Handler;

use Jgut\Sessionware\Traits\HandlerTrait;

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
     *
     * @SuppressWarnings(PMD.UnusedFormalParameter)
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
        $sessionData = $this->driver->get($sessionId);

        $this->driver->touch($sessionId, time() + $this->configuration->getLifetime());

        return $sessionData ? $this->decryptSessionData($sessionData) : serialize([]);
    }

    /**
     * {@inheritdoc}
     */
    public function write($sessionId, $sessionData)
    {
        return $this->driver->set(
            $sessionId,
            $this->encryptSessionData($sessionData),
            time() + $this->configuration->getLifetime()
        );
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
     * @SuppressWarnings(PMD.UnusedFormalParameter)
     */
    public function gc($maxLifetime)
    {
        return true;
    }
}
