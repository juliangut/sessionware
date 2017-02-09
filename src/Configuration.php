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
 * Session configuration.
 */
class Configuration
{
    use SessionTrait;

    const LIFETIME_FLASH    = 300; // 5 minutes
    const LIFETIME_SHORT    = 600; // 10 minutes
    const LIFETIME_NORMAL   = 900; // 15 minutes
    const LIFETIME_DEFAULT  = 1440; // 24 minutes
    const LIFETIME_EXTENDED = 3600; // 1 hour
    const LIFETIME_INFINITE = PHP_INT_MAX; // Around 1145 years (x86_64)

    const TIMEOUT_KEY_DEFAULT = '__SESSIONWARE_TIMEOUT_TIMESTAMP__';

    const SESSION_NAME_DEFAULT = 'PHPSESSID';

    const SESSION_ID_LENGTH = 80;

    /**
     * @var string
     */
    protected $name;

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
    protected $savePath;

    /**
     * @var int
     */
    protected $lifetime;

    /**
     * @var string
     */
    protected $timeoutKey;

    /**
     * Configuration constructor.
     *
     * @param array|\Traversable $configurations
     *
     * @throws \InvalidArgumentException
     */
    public function __construct($configurations = [])
    {
        if (!is_array($configurations) && !$configurations instanceof \Traversable) {
            throw new \InvalidArgumentException('Configurations must be a traversable');
        }

        if ($configurations instanceof \Traversable) {
            $configurations = iterator_to_array($configurations);
        }

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
    protected function getDefaultSessionSettings()
    {
        $lifeTime = $this->getIntegerIniSetting('cookie_lifetime') === 0
            ? $this->getIntegerIniSetting('gc_maxlifetime')
            : min($this->getIntegerIniSetting('cookie_lifetime'), $this->getIntegerIniSetting('gc_maxlifetime'));

        return [
            'name'           => $this->getStringIniSetting('name', static::SESSION_NAME_DEFAULT),
            'savePath'       => $this->getStringIniSetting('save_path', sys_get_temp_dir()),
            'lifetime'       => $lifeTime > 0 ? $lifeTime : static::LIFETIME_DEFAULT,
            'timeoutKey'     => static::TIMEOUT_KEY_DEFAULT,
            'cookiePath'     => $this->getStringIniSetting('cookie_path', '/'),
            'cookieDomain'   => $this->getStringIniSetting('cookie_domain'),
            'cookieSecure'   => $this->hasBoolIniSetting('cookie_secure'),
            'cookieHttpOnly' => $this->hasBoolIniSetting('cookie_httponly'),
        ];
    }

    /**
     * Seed configurations.
     *
     * @param array|\Traversable $configurations
     */
    protected function seedConfigurations($configurations)
    {
        $configs = [
            'name',
            'cookiePath',
            'cookieDomain',
            'cookieSecure',
            'cookieHttpOnly',
            'savePath',
            'lifetime',
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
    public function getName()
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
     * @return static
     */
    public function setName($name)
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
    public function getCookiePath()
    {
        return $this->cookiePath;
    }

    /**
     * Set session cookie path.
     *
     * @param string $cookiePath
     *
     * @return static
     */
    public function setCookiePath($cookiePath)
    {
        $this->cookiePath = $cookiePath;

        return $this;
    }

    /**
     * Get session cookie domain.
     *
     * @return string
     */
    public function getCookieDomain()
    {
        return $this->cookieDomain;
    }

    /**
     * Set session cookie domain.
     *
     * @param string $cookieDomain
     *
     * @return static
     */
    public function setCookieDomain($cookieDomain)
    {
        $this->cookieDomain = $cookieDomain;

        return $this;
    }

    /**
     * Is session cookie HTTPS only.
     *
     * @return bool
     */
    public function isCookieSecure()
    {
        return $this->cookieSecure;
    }

    /**
     * Set session cookie HTTPS only.
     *
     * @param bool $cookieSecure
     *
     * @return static
     */
    public function setCookieSecure($cookieSecure)
    {
        $this->cookieSecure = $cookieSecure === true;

        return $this;
    }

    /**
     * Is session cookie HTTP only.
     *
     * @return bool
     */
    public function isCookieHttpOnly()
    {
        return $this->cookieHttpOnly;
    }

    /**
     * Set session cookie HTTP only.
     *
     * @param bool $cookieHttpOnly
     *
     * @return static
     */
    public function setCookieHttpOnly($cookieHttpOnly)
    {
        $this->cookieHttpOnly = $cookieHttpOnly;

        return $this;
    }

    /**
     * Get session save path.
     *
     * @return string
     */
    public function getSavePath()
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
     * @return static
     */
    public function setSavePath($savePath)
    {
        if (trim($savePath) === '') {
            throw new \InvalidArgumentException('Session save path must be a non empty string');
        }

        $this->savePath = trim($savePath);

        return $this;
    }

    /**
     * Get session lifetime.
     *
     * @return int
     */
    public function getLifetime()
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
     * @return static
     */
    public function setLifetime($lifetime)
    {
        if ((int) $lifetime < 1) {
            throw new \InvalidArgumentException('Session lifetime must be a positive integer');
        }

        $this->lifetime = (int) $lifetime;

        return $this;
    }

    /**
     * Get session timeout control key.
     *
     * @return string
     */
    public function getTimeoutKey()
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
     * @return static
     */
    public function setTimeoutKey($timeoutKey)
    {
        if (trim($timeoutKey) === '') {
            throw new \InvalidArgumentException('Session timeout key must be a non empty string');
        }

        $this->timeoutKey = $timeoutKey;

        return $this;
    }
}
