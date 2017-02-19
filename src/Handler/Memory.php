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
 * In-memory session handler.
 */
class Memory implements Handler
{
    use HandlerTrait;

    /**
     * @var array
     */
    protected $data = [];

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
     *
     * @SuppressWarnings(PMD.UnusedFormalParameter)
     */
    public function read($sessionId)
    {
        return serialize($this->data);
    }

    /**
     * {@inheritdoc}
     *
     * @SuppressWarnings(PMD.UnusedFormalParameter)
     */
    public function write($sessionId, $sessionData)
    {
        $this->data = unserialize($sessionData);

        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @SuppressWarnings(PMD.UnusedFormalParameter)
     */
    public function destroy($sessionId)
    {
        $this->data = [];

        return true;
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
