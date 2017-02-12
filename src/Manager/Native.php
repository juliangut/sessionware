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

use Jgut\Middleware\Sessionware\Configuration;
use Jgut\Middleware\Sessionware\Handler\Handler;
use Jgut\Middleware\Sessionware\Handler\Native as NativeHandler;
use Jgut\Middleware\Sessionware\SessionIniSettingsTrait;

/**
 * Native PHP session manager.
 */
class Native implements Manager
{
    use SessionIniSettingsTrait;

    /**
     * @var Configuration
     */
    protected $configuration;

    /**
     * @var string
     */
    protected $sessionId;

    /**
     * @var bool
     */
    protected $sessionStarted = false;

    /**
     * Session manager constructor.
     *
     * @param Configuration $configuration
     * @param Handler|null  $sessionHandler
     *
     * @throws \RuntimeException
     */
    public function __construct(Configuration $configuration, Handler $sessionHandler = null)
    {
        if (session_status() === PHP_SESSION_DISABLED) {
            // @codeCoverageIgnoreStart
            throw new \RuntimeException('PHP sessions are disabled');
            // @codeCoverageIgnoreEnd
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            throw new \RuntimeException('Session has already been started. Check "session.auto_start" ini setting');
        }

        if (!$this->isCli()) {
            // @codeCoverageIgnoreStart
            $this->verifyIniSettings();
            // @codeCoverageIgnoreEnd
        }

        $this->configuration = $configuration;

        $this->configureSessionSerializer();
        $this->configureSessionGarbageCollector();
        $this->configureSessionSaveHandler($sessionHandler);
    }

    /**
     * {@inheritdoc}
     */
    public function getConfiguration()
    {
        return $this->configuration;
    }

    /**
     * {@inheritdoc}
     */
    public function getSessionId()
    {
        return $this->sessionStarted ? $this->sessionId : null;
    }

    /**
     * {@inheritdoc}
     *
     * @throws \RuntimeException
     */
    public function setSessionId($sessionId)
    {
        if ($this->sessionStarted) {
            throw new \RuntimeException('Session identifier cannot be manually altered once session is started');
        }

        $this->sessionId = $sessionId;

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @throws \RuntimeException
     */
    public function sessionStart()
    {
        if ($this->sessionStarted) {
            return;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            throw new \RuntimeException('Session has already been started. Check "session.auto_start" ini setting');
        }

        if (headers_sent($file, $line)) {
            throw new \RuntimeException(
                sprintf(
                    'PHP session failed to start because headers have already been sent by "%s" at line %d.',
                    $file,
                    $line
                )
            );
        }

        $this->sessionInitialize();

        $this->sessionStarted = true;
    }

    /**
     * Initialize session.
     *
     * @throws \RuntimeException
     */
    final protected function sessionInitialize()
    {
        if ($this->sessionId) {
            session_id($this->sessionId);
        }

        session_start();

        if (session_status() !== PHP_SESSION_ACTIVE) {
            // @codeCoverageIgnoreStart
            throw new \RuntimeException('PHP session failed to start');
            // @codeCoverageIgnoreEnd
        }

        if (!$this->sessionId) {
            $this->sessionId = session_id();
        }
    }

    /**
     * {@inheritdoc}
     *
     * @throws \RuntimeException
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    public function loadSessionData()
    {
        if (!$this->sessionStarted) {
            throw new \RuntimeException('Cannot load data from a not started session');
        }

        if (!isset($_SESSION)) {
            return [];
        }

        $keyPattern = '/^' . $this->configuration->getName() . '\./';
        $data = [];
        foreach ($_SESSION as $key => $value) {
            if (preg_match($keyPattern, $key)) {
                $data[preg_replace($keyPattern, '', $key)] = $value;
            }
        }

        unset($_SESSION);

        return $data;
    }

    /**
     * {@inheritdoc}
     *
     * @throws \RuntimeException
     *
     * @SuppressWarnings(PMD.Superglobals)
     */
    public function sessionEnd(array $data = [])
    {
        if (!$this->sessionStarted) {
            throw new \RuntimeException('Cannot end a not started session');
        }

        $keyPrefix = $this->configuration->getName();
        $sessionData = [];
        foreach ($data as $key => $value) {
            $sessionData[$keyPrefix . '.' . $key] = $value;
        }
        $_SESSION = $sessionData;

        session_write_close();

        unset($_SESSION);

        $this->sessionStarted = false;
        $this->sessionId = null;

        if (session_status() === PHP_SESSION_ACTIVE) {
            // @codeCoverageIgnoreStart
            throw new \RuntimeException('PHP session failed to finish');
            // @codeCoverageIgnoreEnd
        }
    }

    /**
     * {@inheritdoc}
     *
     * @throws \RuntimeException
     *
     * @SuppressWarnings(PMD.Superglobals)
     */
    public function sessionReset()
    {
        if (!$this->sessionStarted) {
            throw new \RuntimeException('Cannot reset a not started session');
        }

        unset($_SESSION);
        session_unset();
        session_destroy();

        if (session_status() === PHP_SESSION_ACTIVE) {
            // @codeCoverageIgnoreStart
            throw new \RuntimeException('PHP session failed to finish');
            // @codeCoverageIgnoreEnd
        }

        $this->sessionStarted = false;

        $this->sessionId = $this->getNewSessionId();

        $this->sessionStart();
    }

    /**
     * {@inheritdoc}
     */
    public function isSessionStarted()
    {
        return $this->sessionStarted;
    }

    /**
     * {@inheritdoc}
     */
    public function shouldRegenerate()
    {
        return strlen($this->sessionId) !== Configuration::SESSION_ID_LENGTH;
    }

    /**
     * Check if running on CLI.
     *
     * @return bool
     */
    protected function isCli()
    {
        return \PHP_SAPI === 'cli';
    }

    /**
     * Verify session ini settings.
     *
     * @throws \RuntimeException
     *
     * @codeCoverageIgnore
     */
    final protected function verifyIniSettings()
    {
        if ($this->hasBoolIniSetting('use_trans_sid') !== false) {
            throw new \RuntimeException('"session.use_trans_sid" ini setting must be set to false');
        }

        if ($this->hasBoolIniSetting('use_cookies') !== true) {
            throw new \RuntimeException('"session.use_cookies" ini setting must be set to true');
        }

        if ($this->hasBoolIniSetting('use_only_cookies') !== true) {
            throw new \RuntimeException('"session.use_only_cookies" ini setting must be set to true');
        }

        if ($this->hasBoolIniSetting('use_strict_mode') !== false) {
            throw new \RuntimeException('"session.use_strict_mode" ini setting must be set to false');
        }

        if ($this->hasBoolIniSetting('cache_limiter') !== null) {
            throw new \RuntimeException('"session.cache_limiter" ini setting must be set to empty string');
        }
    }

    /**
     * Configure session data serializer.
     */
    protected function configureSessionSerializer()
    {
        // Use better session serializer when available
        if ($this->getIniSetting('serialize_handler') !== 'php_serialize'
            && version_compare(PHP_VERSION, '5.5.4', '>=')
        ) {
            // @codeCoverageIgnoreStart
            $this->setIniSetting('serialize_handler', 'php_serialize');
            // @codeCoverageIgnoreEnd
        }
    }

    /**
     * Configure session timeout.
     */
    protected function configureSessionGarbageCollector()
    {
        $this->setIniSetting('gc_maxlifetime', $this->configuration->getLifetime());
    }

    /**
     * Configure session save handler.
     *
     * @param Handler|null $sessionHandler
     */
    protected function configureSessionSaveHandler(Handler $sessionHandler = null)
    {
        if ($sessionHandler === null) {
            $sessionHandler = new NativeHandler();
        }

        $sessionHandler->setConfiguration($this->configuration);

        session_register_shutdown();
        session_set_save_handler($sessionHandler, false);
    }

    /**
     * Generates cryptographically secure session identifier.
     *
     * @param int $length
     *
     * @return string
     */
    final protected function getNewSessionId($length = Configuration::SESSION_ID_LENGTH)
    {
        return substr(
            preg_replace('/[^a-zA-Z0-9-]+/', '', base64_encode(random_bytes((int) $length))),
            0,
            (int) $length
        );
    }
}
