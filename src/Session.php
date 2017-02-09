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

use League\Event\EmitterAwareInterface;
use League\Event\EmitterTrait;
use League\Event\Event;

/**
 * Session helper.
 *
 * @SuppressWarnings(PHPMD.Superglobals)
 */
class Session implements EmitterAwareInterface
{
    use SessionTrait;
    use EmitterTrait;

    /**
     * @var Configuration
     */
    protected $configuration;

    /**
     * Session constructor.
     *
     * @param Configuration $configuration
     */
    public function __construct(Configuration $configuration)
    {
        $this->configuration = $configuration;
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
        return array_key_exists($key, $_SESSION);
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
        return $this->has($key) ? $_SESSION[$key] : $default;
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
        $_SESSION[$key] = $value;

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
        if ($this->has($key)) {
            unset($_SESSION[$key]);
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
        $_SESSION = [];

        return $this;
    }

    /**
     * Manage session timeout.
     */
    public function manageTimeout()
    {
        $timeoutKey = $this->configuration->getTimeoutKey();

        if (array_key_exists($timeoutKey, $_SESSION) && $_SESSION[$timeoutKey] < time()) {
            $this->emit(Event::named('pre.session_timeout'), session_id(), $this);

            $this->resetSession();

            $this->emit(Event::named('post.session_timeout'), session_id(), $this);
        }

        $_SESSION[$timeoutKey] = time() + $this->configuration->getLifetime();
    }

    /**
     * Regenerate session identifier keeping session parameters.
     *
     * @throws \RuntimeException
     */
    public function regenerateSessionId()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new \RuntimeException('Cannot regenerate id on a not started session');
        }

        $sessionBackup = is_array($_SESSION) ? $_SESSION : [];

        $this->resetSession();

        foreach ($sessionBackup as $param => $value) {
            $_SESSION[$param] = $value;
        }
    }
}
