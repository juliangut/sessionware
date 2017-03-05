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

namespace Jgut\Sessionware\Manager;

use Jgut\Sessionware\Configuration;

/**
 * Session manager interface.
 */
interface Manager
{
    /**
     * Get session configuration.
     *
     * @return \Jgut\Sessionware\Configuration
     */
    public function getConfiguration() : Configuration;

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
     * Has session been started.
     *
     * @return bool
     */
    public function isSessionStarted() : bool;

    /**
     * Has session been destroyed.
     *
     * @return bool
     */
    public function isSessionDestroyed() : bool;

    /**
     * Should session identifier be regenerated.
     *
     * @return bool
     */
    public function shouldRegenerateId() : bool;
}
