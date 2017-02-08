<?php

/*
 * sessionware (https://github.com/juliangut/sessionware).
 * PSR7 session management middleware.
 *
 * @license BSD-3-Clause
 * @link https://github.com/juliangut/sessionware
 * @author Julián Gutiérrez <juliangut@gmail.com>
 */

namespace Jgut\Middleware;

/**
 * Session helper.
 *
 * @SuppressWarnings(PHPMD.Superglobals)
 */
class Session
{
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
     * @return $this
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
     * @return $this
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
     * @return $this
     */
    public function clear()
    {
        $_SESSION = [];

        return $this;
    }

    /**
     * Regenerate session.
     *
     * @return $this
     */
    public function regenerate()
    {
        static::regenerateSessionId();

        return $this;
    }

    /**
     * Regenerate session identifier keeping session parameters.
     */
    public static function regenerateSessionId()
    {
        $sessionParams = $_SESSION;

        $_SESSION = [];
        session_unset();
        session_destroy();

        session_id(SessionWare::generateSessionId());

        session_start();

        foreach ($sessionParams as $param => $value) {
            $_SESSION[$param] = $value;
        }
    }
}
