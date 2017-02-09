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

/**
 * Session management trait.
 */
trait SessionTrait
{
    /**
     * Get string ini setting.
     *
     * @param string $setting
     * @param string $default
     *
     * @return string
     */
    protected function getStringIniSetting($setting, $default = '')
    {
        $setting = $this->getIniSetting($setting);

        return !empty(trim($setting)) ? $setting : $default;
    }

    /**
     * Get integer ini setting.
     *
     * @param string $setting
     *
     * @return int
     */
    protected function getIntegerIniSetting($setting)
    {
        return (int) $this->getIniSetting($setting);
    }

    /**
     * Get boolean ini setting.
     *
     * @param string $setting
     *
     * @return bool
     */
    protected function hasBoolIniSetting($setting)
    {
        return (bool) $this->getIniSetting($setting);
    }

    /**
     * Get raw init setting.
     *
     * @param string $setting
     *
     * @return mixed
     */
    protected function getIniSetting($setting)
    {
        return ini_get($this->normalizeSessionIniSetting($setting));
    }

    /**
     * Set session ini setting.
     *
     * @param string $setting
     * @param mixed  $value
     */
    protected function setIniSetting($setting, $value)
    {
        ini_set($this->normalizeSessionIniSetting($setting), $value);
    }

    /**
     * Normalize session setting name to start with 'session.'.
     *
     * @param string $setting
     *
     * @return string
     */
    private function normalizeSessionIniSetting($setting)
    {
        return strpos($setting, 'session.') !== 0 ? 'session.' . $setting : $setting;
    }

    /**
     * Close previous session and create a new empty one.
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    protected function resetSession()
    {
        $_SESSION = [];
        session_unset();
        session_destroy();

        session_id($this->getNewSessionId());

        session_start();
    }

    /**
     * Generates cryptographically secure session identifier.
     *
     * @param int $length
     *
     * @return string
     */
    protected function getNewSessionId($length = Configuration::SESSION_ID_LENGTH)
    {
        return substr(
            preg_replace('/[^a-zA-Z0-9-]+/', '', base64_encode(random_bytes((int) $length))),
            0,
            (int) $length
        );
    }
}
