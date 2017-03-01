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

namespace Jgut\Sessionware;

use Jgut\Sessionware\Traits\NativeSessionTrait;

/**
 * Session configuration.
 */
class Configuration
{
    use NativeSessionTrait;

    const LIFETIME_FLASH    = 300; // 5 minutes
    const LIFETIME_SHORT    = 600; // 10 minutes
    const LIFETIME_NORMAL   = 900; // 15 minutes
    const LIFETIME_DEFAULT  = 1440; // 24 minutes
    const LIFETIME_EXTENDED = 3600; // 1 hour
    const LIFETIME_INFINITE = PHP_INT_MAX; // Around 1145 years (x86_64)

    const TIMEOUT_KEY_DEFAULT = '__SESSIONWARE_TIMEOUT__';

    const SESSION_NAME_DEFAULT = 'PHPSESSID';

    const SESSION_ID_LENGTH = 80;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $savePath;

    /**
     * @var int
     */
    protected $lifetime;

    /**
     * @var string
     */
    protected $cookiePath;

    /**
     * @var string
     */
    protected $cookieDomain;

    /**
     * @var bool
     */
    protected $cookieSecure;

    /**
     * @var bool
     */
    protected $cookieHttpOnly;

    /**
     * @var string
     */
    protected $encryptionKey;

    /**
     * @var string
     */
    protected $timeoutKey;

    /**
     * Configuration constructor.
     *
     * @param array $configurations
     */
    public function __construct(array $configurations = [])
    {
        $configurations = array_merge(
            $this->getDefaultSessionSettings(),
            $configurations
        );

        $this->seedConfigurations($configurations);
    }

    /**
     * Retrieve default session settings.
     *
     * @return array
     */
    protected function getDefaultSessionSettings() : array
    {
        $sessionLifetime = $this->getIntegerIniSetting('cookie_lifetime') === 0
            ? $this->getIntegerIniSetting('gc_maxlifetime')
            : min($this->getIntegerIniSetting('cookie_lifetime'), $this->getIntegerIniSetting('gc_maxlifetime'));
        $sessionName = session_name() !== static::SESSION_NAME_DEFAULT ? session_name() : static::SESSION_NAME_DEFAULT;

        return [
            'name'           => $this->getStringIniSetting('name', $sessionName),
            'savePath'       => $this->getStringIniSetting('save_path', sys_get_temp_dir()),
            'lifetime'       => $sessionLifetime > 0 ? $sessionLifetime : static::LIFETIME_DEFAULT,
            'cookiePath'     => $this->getStringIniSetting('cookie_path', '/'),
            'cookieDomain'   => $this->getStringIniSetting('cookie_domain'),
            'cookieSecure'   => $this->hasBoolIniSetting('cookie_secure'),
            'cookieHttpOnly' => $this->hasBoolIniSetting('cookie_httponly'),
            'timeoutKey'     => static::TIMEOUT_KEY_DEFAULT,
        ];
    }

    /**
     * Seed configurations.
     *
     * @param array $configurations
     */
    protected function seedConfigurations(array $configurations)
    {
        $configs = [
            'name',
            'cookiePath',
            'cookieDomain',
            'cookieSecure',
            'cookieHttpOnly',
            'savePath',
            'lifetime',
            'encryptionKey',
            'timeoutKey',
        ];

        foreach ($configs as $config) {
            if (isset($configurations[$config])) {
                $callback = [$this, 'set' . ucfirst($config)];

                call_user_func($callback, $configurations[$config]);
            }
        }
    }

    /**
     * Get session name.
     *
     * @return string
     */
    public function getName() : string
    {
        return $this->name;
    }

    /**
     * Set session name.
     *
     * @param string $name
     *
     * @throws \InvalidArgumentException
     *
     * @return self
     */
    public function setName(string $name)
    {
        if (trim($name) === '') {
            throw new \InvalidArgumentException('Session name must be a non empty string');
        }

        $this->name = $name;

        return $this;
    }

    /**
     * Get session cookie path.
     *
     * @return string
     */
    public function getCookiePath() : string
    {
        return $this->cookiePath;
    }

    /**
     * Set session cookie path.
     *
     * @param string $cookiePath
     *
     * @return self
     */
    public function setCookiePath(string $cookiePath)
    {
        $this->cookiePath = $cookiePath;

        return $this;
    }

    /**
     * Get session cookie domain.
     *
     * @return string
     */
    public function getCookieDomain() : string
    {
        return $this->cookieDomain;
    }

    /**
     * Set session cookie domain.
     *
     * @param string $cookieDomain
     *
     * @return self
     */
    public function setCookieDomain(string $cookieDomain)
    {
        $this->cookieDomain = $cookieDomain;

        return $this;
    }

    /**
     * Is session cookie HTTPS only.
     *
     * @return bool
     */
    public function isCookieSecure() : bool
    {
        return $this->cookieSecure;
    }

    /**
     * Set session cookie HTTPS only.
     *
     * @param bool $cookieSecure
     *
     * @return self
     */
    public function setCookieSecure(bool $cookieSecure)
    {
        $this->cookieSecure = $cookieSecure;

        return $this;
    }

    /**
     * Is session cookie HTTP only.
     *
     * @return bool
     */
    public function isCookieHttpOnly() : bool
    {
        return $this->cookieHttpOnly;
    }

    /**
     * Set session cookie HTTP only.
     *
     * @param bool $cookieHttpOnly
     *
     * @return self
     */
    public function setCookieHttpOnly(bool $cookieHttpOnly)
    {
        $this->cookieHttpOnly = $cookieHttpOnly;

        return $this;
    }

    /**
     * Get session save path.
     *
     * @return string
     */
    public function getSavePath() : string
    {
        return $this->savePath;
    }

    /**
     * Set session save path.
     *
     * @param string $savePath
     *
     * @throws \InvalidArgumentException
     *
     * @return self
     */
    public function setSavePath(string $savePath)
    {
        if (trim($savePath) === '') {
            throw new \InvalidArgumentException('Session save path must be a non empty string');
        }

        $this->savePath = rtrim(trim($savePath), DIRECTORY_SEPARATOR);

        return $this;
    }

    /**
     * Get session lifetime.
     *
     * @return int
     */
    public function getLifetime() : int
    {
        return $this->lifetime;
    }

    /**
     * Set session lifetime.
     *
     * @param int $lifetime
     *
     * @throws \InvalidArgumentException
     *
     * @return self
     */
    public function setLifetime(int $lifetime)
    {
        if ((int) $lifetime < 1) {
            throw new \InvalidArgumentException('Session lifetime must be a positive integer');
        }

        $this->lifetime = (int) $lifetime;

        return $this;
    }

    /**
     * Set session encryption key.
     *
     * @return string|null
     */
    public function getEncryptionKey()
    {
        return $this->encryptionKey;
    }

    /**
     * Set session encryption key.
     *
     * @param string $encryptionKey
     *
     * @throws \InvalidArgumentException
     *
     * @return self
     */
    public function setEncryptionKey(string $encryptionKey) : self
    {
        if (trim($encryptionKey) === '') {
            throw new \InvalidArgumentException('Session encryption key must be a non empty string');
        }

        $this->encryptionKey = $encryptionKey;

        return $this;
    }

    /**
     * Get session timeout control key.
     *
     * @return string
     */
    public function getTimeoutKey() : string
    {
        return $this->timeoutKey;
    }

    /**
     * Set session timeout control key.
     *
     * @param string $timeoutKey
     *
     * @throws \InvalidArgumentException
     *
     * @return self
     */
    public function setTimeoutKey(string $timeoutKey)
    {
        if (trim($timeoutKey) === '') {
            throw new \InvalidArgumentException('Session timeout key must be a non empty string');
        }

        $this->timeoutKey = $timeoutKey;

        return $this;
    }
}
