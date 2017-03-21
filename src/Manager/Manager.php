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
    public function getConfiguration(): Configuration;

    /**
     * Get session identifier.
     *
     * @return string
     */
    public function getId(): string;

    /**
     * @param string $sessionId
     *
     * @return self
     */
    public function setId(string $sessionId);

    /**
     * Start session.
     *
     * @return array
     */
    public function start(): array;

    /**
     * Save session data and end session.
     *
     * @param array $data
     */
    public function close(array $data = []);

    /**
     * Should session identifier be regenerated.
     *
     * @return bool
     */
    public function shouldRegenerateId(): bool;

    /**
     * Regenerate session identifier.
     */
    public function regenerateId();

    /**
     * Destroy session.
     */
    public function destroy();

    /**
     * Has session been started.
     *
     * @return bool
     */
    public function isStarted(): bool;

    /**
     * Has session been destroyed.
     *
     * @return bool
     */
    public function isDestroyed(): bool;
}
