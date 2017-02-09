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
 * Session ini settings management trait.
 */
trait SessionIniSettingTrait
{
    /**
     * Retrieve session ini setting.
     *
     * @param string     $setting
     * @param mixed|null $default
     *
     * @return mixed
     */
    protected function getSessionIniSetting($setting, $default = null)
    {
        $setting = ini_get($this->normalizeSessionIniSetting($setting));

        if (is_numeric($setting)) {
            return (int) $setting;
        }

        $setting = trim($setting);

        return $setting !== '' ? $setting : $default;
    }

    /**
     * Set session ini setting.
     *
     * @param string $setting
     * @param mixed  $value
     */
    protected function setSessionIniSetting($setting, $value)
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
}
