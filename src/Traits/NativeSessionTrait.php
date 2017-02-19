<?php

/*
 * sessionware (https://github.com/juliangut/sessionware).
 * PSR7 compatible session management.
 *
 * @license BSD-3-Clause
 * @link https://github.com/juliangut/sessionware
 * @author Julián Gutiérrez <juliangut@gmail.com>
 */

namespace Jgut\Sessionware\Traits;

/**
 * Native session management trait.
 */
trait NativeSessionTrait
{
    /**
     * Get string ini setting.
     *
     * @param string $setting
     * @param string $default
     *
     * @return string
     */
    protected function getStringIniSetting(string $setting, string $default = '') : string
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
    protected function getIntegerIniSetting(string $setting) : int
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
    protected function hasBoolIniSetting(string $setting) : bool
    {
        return (bool) $this->getIniSetting($setting);
    }

    /**
     * Get raw init setting.
     *
     * @param string $setting
     *
     * @return string
     */
    protected function getIniSetting(string $setting) : string
    {
        return ini_get($this->normalizeSessionIniSetting($setting));
    }

    /**
     * Set session ini setting.
     *
     * @param string $setting
     * @param mixed  $value
     */
    protected function setIniSetting(string $setting, $value)
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
    private function normalizeSessionIniSetting(string $setting) : string
    {
        return strpos($setting, 'session.') !== 0 ? 'session.' . $setting : $setting;
    }
}
