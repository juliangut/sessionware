<?php

/*
 * sessionware (https://github.com/juliangut/sessionware).
 * PSR7 session management middleware.
 *
 * @license BSD-3-Clause
 * @link https://github.com/juliangut/sessionware
 * @author Julián Gutiérrez <juliangut@gmail.com>
 */

namespace Jgut\Middleware\Sessionware;

use Jgut\Middleware\Sessionware\Manager\Manager;
use League\Event\EmitterAwareInterface;
use League\Event\EmitterTrait;
use League\Event\Event;

/**
 * Session helper.
 */
class Session implements EmitterAwareInterface
{
    use EmitterTrait;

    /**
     * @var Manager
     */
    protected $sessionManager;

    /**
     * Session data.
     *
     * @var array
     */
    protected $data = [];

    /**
     * Session constructor.
     *
     * @param Manager $sessionManager
     */
    public function __construct(Manager $sessionManager)
    {
        $this->sessionManager = $sessionManager;
    }

    /**
     * Start session.
     *
     * @throws \RuntimeException
     */
    public function start()
    {
        if ($this->isActive()) {
            return;
        }

        $this->data = $this->sessionManager->sessionStart();

        if ($this->sessionManager->shouldRegenerateId()) {
            $this->regenerateId();
        }

        $this->manageTimeout();
    }

    /**
     * Regenerate session identifier keeping parameters.
     *
     * @throws \RuntimeException
     *
     * @SuppressWarnings(PMD.Superglobals)
     */
    public function regenerateId()
    {
        if (!$this->isActive()) {
            throw new \RuntimeException('Cannot regenerate a not started session');
        }

        $this->sessionManager->sessionRegenerateId();
    }

    /**
     * Close session.
     *
     * @SuppressWarnings(PMD.Superglobals)
     */
    public function close()
    {
        if (!$this->isActive()) {
            return;
        }

        $this->sessionManager->sessionEnd($this->data);
    }

    /**
     * Destroy session.
     *
     * @throws \RuntimeException
     */
    public function destroy()
    {
        if (!$this->isActive()) {
            throw new \RuntimeException('Cannot destroy a not started session');
        }

        $this->sessionManager->sessionDestroy();

        $this->data = [];
    }

    /**
     * Is there an active session.
     *
     * @return bool
     */
    public function isActive()
    {
        return $this->sessionManager->isSessionStarted();
    }

    /**
     * Get session identifier.
     *
     * @return string|null
     */
    public function getId()
    {
        return $this->sessionManager->getSessionId();
    }

    /**
     * Session parameter existence.
     *
     * @param string $key
     *
     * @return bool
     */
    public function has($key)
    {
        return array_key_exists($key, $this->data);
    }

    /**
     * Retrieve session parameter.
     *
     * @param string     $key
     * @param mixed|null $default
     *
     * @return mixed
     */
    public function get($key, $default = null)
    {
        return array_key_exists($key, $this->data) ? $this->data[$key] : $default;
    }

    /**
     * Set session parameter.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return static
     */
    public function set($key, $value)
    {
        $this->data[$key] = $value;

        return $this;
    }

    /**
     * Remove session parameter.
     *
     * @param string $key
     *
     * @return static
     */
    public function remove($key)
    {
        if (array_key_exists($key, $this->data)) {
            unset($this->data[$key]);
        }

        return $this;
    }

    /**
     * Remove all session parameters.
     *
     * @return static
     */
    public function clear()
    {
        $this->data = [];

        $this->setTimeout();

        return $this;
    }

    /**
     * Get session timeout time.
     *
     * @return int
     */
    public function getTimeout()
    {
        return $this->data[$this->getConfiguration()->getTimeoutKey()];
    }

    /**
     * Set session timeout time.
     */
    protected function setTimeout()
    {
        $this->data[$this->getConfiguration()->getTimeoutKey()] = time() + $this->getConfiguration()->getLifetime();
    }

    /**
     * Manage session timeout.
     *
     * @throws \RuntimeException
     */
    protected function manageTimeout()
    {
        $timeoutKey = $this->getConfiguration()->getTimeoutKey();

        if (array_key_exists($timeoutKey, $this->data) && $this->data[$timeoutKey] < time()) {
            $this->emit(Event::named('pre.session_timeout'), session_id(), $this);

            $this->sessionManager->sessionRegenerateId();

            $this->emit(Event::named('post.session_timeout'), session_id(), $this);
        }

        $this->setTimeout();
    }

    /**
     * Get session configuration.
     *
     * @return Configuration
     */
    protected function getConfiguration()
    {
        return $this->sessionManager->getConfiguration();
    }
}
