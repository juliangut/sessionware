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

namespace Jgut\Sessionware\Manager;

use Jgut\Sessionware\Configuration;
use Jgut\Sessionware\Handler\Handler;
use Jgut\Sessionware\Handler\Native as NativeHandler;
use Jgut\Sessionware\Traits\NativeSessionTrait;

/**
 * Native PHP session manager.
 */
class Native implements Manager
{
    use NativeSessionTrait;

    /**
     * @var Configuration
     */
    protected $configuration;

    /**
     * Session handler.
     *
     * @var Handler
     */
    protected $sessionHandler;

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

        $this->configuration = $configuration;

        if ($sessionHandler === null) {
            $sessionHandler = new NativeHandler();
        }
        $sessionHandler->setConfiguration($configuration);

        $this->sessionHandler = $sessionHandler;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfiguration() : Configuration
    {
        return $this->configuration;
    }

    /**
     * {@inheritdoc}
     */
    public function getSessionId() : string
    {
        return $this->sessionStarted ? $this->sessionId : '';
    }

    /**
     * {@inheritdoc}
     *
     * @throws \RuntimeException
     */
    public function setSessionId(string $sessionId)
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
     *
     * @return array|null
     */
    public function sessionStart() : array
    {
        $this->verifyIniSettings();

        if ($this->sessionStarted || session_status() === PHP_SESSION_ACTIVE) {
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

        $this->configureSession();
        $this->initializeSession();

        $this->sessionStarted = true;

        return $this->loadSessionData();
    }

    /**
     * Configure session settings.
     */
    protected function configureSession()
    {
        // Use better session serializer when available
        if ($this->getIniSetting('serialize_handler') !== 'php_serialize') {
            // @codeCoverageIgnoreStart
            $this->setIniSetting('serialize_handler', 'php_serialize');
            // @codeCoverageIgnoreEnd
        }

        $this->setIniSetting('gc_maxlifetime', $this->configuration->getLifetime());

        session_register_shutdown();
        session_set_save_handler($this->sessionHandler, false);
    }

    /**
     * Initialize session.
     *
     * @throws \RuntimeException
     */
    final protected function initializeSession()
    {
        if ($this->sessionId) {
            session_id($this->sessionId);
        }

        session_name($this->configuration->getName());

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
     * Retrieve session saved data.
     *
     * @return array
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    final protected function loadSessionData()
    {
        $keyPattern = '/^' . $this->configuration->getName() . '\./';
        $data = [];
        foreach ($_SESSION as $key => $value) {
            if (preg_match($keyPattern, $key)) {
                $data[preg_replace($keyPattern, '', $key)] = $value;
            }
        }

        $_SESSION = null;

        return $data;
    }

    /**
     * {@inheritdoc}
     *
     * @throws \RuntimeException
     *
     * @SuppressWarnings(PMD.Superglobals)
     */
    public function sessionRegenerateId()
    {
        if (!$this->sessionStarted) {
            throw new \RuntimeException('Cannot regenerate id a not started session');
        }

        $_SESSION = null;
        session_unset();
        session_destroy();

        $this->sessionStarted = false;

        if (session_status() === PHP_SESSION_ACTIVE) {
            // @codeCoverageIgnoreStart
            throw new \RuntimeException('PHP session failed to regenerate id');
            // @codeCoverageIgnoreEnd
        }

        $this->sessionId = $this->getNewSessionId();

        $this->sessionStart();
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

        $_SESSION = null;

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
    public function sessionDestroy()
    {
        if (!$this->sessionStarted) {
            throw new \RuntimeException('Cannot destroy a not started session');
        }

        unset($_SESSION);
        session_unset();
        session_destroy();

        $this->sessionStarted = false;

        if (session_status() === PHP_SESSION_ACTIVE) {
            // @codeCoverageIgnoreStart
            throw new \RuntimeException('PHP session failed to finish');
            // @codeCoverageIgnoreEnd
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isSessionStarted() : bool
    {
        return $this->sessionStarted;
    }

    /**
     * {@inheritdoc}
     */
    public function shouldRegenerateId() : bool
    {
        return strlen($this->sessionId) !== Configuration::SESSION_ID_LENGTH;
    }

    /**
     * Verify session ini settings.
     *
     * @throws \RuntimeException
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

        if ($this->getStringIniSetting('cache_limiter') !== '') {
            throw new \RuntimeException('"session.cache_limiter" ini setting must be set to empty string');
        }
    }

    /**
     * Generates cryptographically secure session identifier.
     *
     * @param int $length
     *
     * @return string
     */
    private function getNewSessionId($length = Configuration::SESSION_ID_LENGTH)
    {
        return substr(
            preg_replace('/[^a-zA-Z0-9-]+/', '', base64_encode(random_bytes((int) $length))),
            0,
            (int) $length
        );
    }
}
