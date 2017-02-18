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
     * @return string
     */
    public function getSessionId() : string;

    /**
     * @param string $sessionId
     *
     * @return self
     */
    public function setSessionId(string $sessionId);

    /**
     * Start session.
     *
     * @return array
     */
    public function sessionStart() : array;

    /**
     * Save session data and end session.
     *
     * @param array $data
     */
    public function sessionEnd(array $data = []);

    /**
     * Regenerate session identifier.
     */
    public function sessionRegenerateId();

    /**
     * Destroy session.
     */
    public function sessionDestroy();

    /**
     * Is session started.
     *
     * @return bool
     */
    public function isSessionStarted() : bool;

    /**
     * Should session identifier be regenerated.
     *
     * @return bool
     */
    public function shouldRegenerateId() : bool;
}
