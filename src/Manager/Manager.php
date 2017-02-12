<?php

/*
 * sessionware (https://github.com/juliangut/sessionware).
 * PSR7 session management middleware.
 *
 * @license BSD-3-Clause
 * @link https://github.com/juliangut/sessionware
 * @author Julián Gutiérrez <juliangut@gmail.com>
 */

namespace Jgut\Middleware\Sessionware\Manager;

/**
 * Session manager interface.
 */
interface Manager
{
    /**
     * Get session configuration.
     *
     * @return \Jgut\Middleware\Sessionware\Configuration
     */
    public function getConfiguration();

    /**
     * Get session identifier.
     *
     * @return string|null
     */
    public function getSessionId();

    /**
     * @param string $sessionId
     *
     * @return self
     */
    public function setSessionId($sessionId);

    /**
     * Start session.
     */
    public function sessionStart();

    /**
     * Save session data and end session.
     *
     * @param array $data
     */
    public function sessionEnd(array $data = []);

    /**
     * Reset session.
     */
    public function sessionReset();

    /**
     * Is session started.
     *
     * @return bool
     */
    public function isSessionStarted();

    /**
     * Should session be regenerated.
     *
     * @return bool
     */
    public function shouldRegenerate();

    /**
     * Retrieve loaded session data.
     *
     * @return array
     */
    public function loadSessionData();
}
