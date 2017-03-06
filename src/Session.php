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

use Jgut\Sessionware\Manager\Manager;
use League\Event\EmitterAwareInterface;
use League\Event\EmitterTrait;
use League\Event\Event;

/**
 * Session helper.
 */
class Session implements EmitterAwareInterface
{
    use EmitterTrait;

    /**
     * Session manager.
     *
     * @var Manager
     */
    protected $sessionManager;

    /**
     * Session initial data.
     *
     * @var array
     */
    protected $originalData;

    /**
     * Session data.
     *
     * @var array
     */
    protected $data;

    /**
     * Session constructor.
     *
     * @param Manager $sessionManager
     * @param array   $initialData
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(Manager $sessionManager, array $initialData = [])
    {
        $this->sessionManager = $sessionManager;
        $this->data = [];

        foreach ($initialData as $key => $value) {
            $this->set($key, $value);
        }

        $this->originalData = $this->data;

        register_shutdown_function([$this, 'close']);
    }

    /**
     * Get session manager.
     *
     * @return Manager
     */
    public function getManager() : Manager
    {
        return $this->sessionManager;
    }

    /**
     * Start session.
     *
     * @throws \RuntimeException
     */
    public function start()
    {
        if ($this->isActive()) {
            return;
        }

        $this->emit(Event::named('pre.start'), $this);

        $this->originalData = $this->data = array_merge($this->data, $this->sessionManager->sessionStart());

        if ($this->sessionManager->shouldRegenerateId()) {
            $this->regenerateId();
        }

        $this->emit(Event::named('post.start'), $this);

        $this->manageTimeout();
    }

    /**
     * Regenerate session identifier keeping parameters.
     *
     * @throws \RuntimeException
     */
    public function regenerateId()
    {
        if (!$this->isActive()) {
            throw new \RuntimeException('Cannot regenerate a not started session');
        }

        $this->emit(Event::named('pre.regenerate_id'), $this);

        $this->sessionManager->sessionRegenerateId();

        $this->emit(Event::named('post.regenerate_id'), $this);
    }

    /**
     * Revert session to its original data.
     */
    public function reset()
    {
        if (!$this->isActive()) {
            return;
        }

        $this->emit(Event::named('pre.reset'), $this);

        $this->data = $this->originalData;

        $this->emit(Event::named('post.reset'), $this);
    }

    /**
     * Close session keeping original session data.
     */
    public function abort()
    {
        if (!$this->isActive()) {
            return;
        }

        $this->emit(Event::named('pre.abort'), $this);

        $this->sessionManager->sessionEnd($this->originalData);

        $this->emit(Event::named('post.abort'), $this);
    }

    /**
     * Close session.
     */
    public function close()
    {
        if (!$this->isActive()) {
            return;
        }

        $this->emit(Event::named('pre.close'), $this);

        $this->sessionManager->sessionEnd($this->data);

        $this->emit(Event::named('post.close'), $this);
    }

    /**
     * Destroy session.
     *
     * @throws \RuntimeException
     */
    public function destroy()
    {
        if (!$this->isActive()) {
            throw new \RuntimeException('Cannot destroy a not started session');
        }

        $this->emit(Event::named('pre.destroy'), $this);

        $this->sessionManager->sessionDestroy();

        $this->emit(Event::named('post.destroy'), $this);

        $this->originalData = $this->data = [];
    }

    /**
     * Has session been started.
     *
     * @return bool
     */
    public function isActive() : bool
    {
        return $this->sessionManager->isSessionStarted();
    }

    /**
     * Has session been destroyed.
     *
     * @return bool
     */
    public function isDestroyed() : bool
    {
        return $this->sessionManager->isSessionDestroyed();
    }

    /**
     * Get session identifier.
     *
     * @return string
     */
    public function getId() : string
    {
        return $this->sessionManager->getSessionId();
    }

    /**
     * Set session identifier.
     *
     * @param string $sessionId
     *
     * @throws \RuntimeException
     */
    public function setId(string $sessionId)
    {
        if ($this->isActive() || $this->isDestroyed()) {
            throw new \RuntimeException('Cannot set session id on started or destroyed sessions');
        }

        $this->sessionManager->setSessionId($sessionId);
    }

    /**
     * Session parameter existence.
     *
     * @param string $key
     *
     * @return bool
     */
    public function has(string $key) : bool
    {
        return array_key_exists($key, $this->data);
    }

    /**
     * Retrieve session parameter.
     *
     * @param string     $key
     * @param mixed|null $default
     *
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        return array_key_exists($key, $this->data) ? $this->data[$key] : $default;
    }

    /**
     * Set session parameter.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @throws \InvalidArgumentException
     *
     * @return self
     */
    public function set(string $key, $value) : self
    {
        $this->verifyScalarValue($value);

        $this->data[$key] = $value;

        return $this;
    }

    /**
     * Verify only scalar values allowed.
     *
     * @param string|int|float|bool|array $value
     *
     * @throws \InvalidArgumentException
     */
    final protected function verifyScalarValue($value)
    {
        if (is_array($value)) {
            foreach ($value as $val) {
                $this->verifyScalarValue($val);
            }
        }

        if (!is_scalar($value)) {
            throw new \InvalidArgumentException(sprintf('Session values must be scalars, %s given', gettype($value)));
        }
    }

    /**
     * Remove session parameter.
     *
     * @param string $key
     *
     * @return self
     */
    public function remove(string $key) : self
    {
        if (array_key_exists($key, $this->data)) {
            unset($this->data[$key]);
        }

        return $this;
    }

    /**
     * Remove all session parameters.
     *
     * @return self
     */
    public function clear() : self
    {
        $timeoutKey = $this->getConfiguration()->getTimeoutKey();
        $sessionTimeout = array_key_exists($timeoutKey, $this->data)
            ? $this->data[$timeoutKey]
            : time() + $this->getConfiguration()->getLifetime();

        $this->data = [$timeoutKey => $sessionTimeout];

        return $this;
    }

    /**
     * Manage session timeout.
     *
     * @throws \RuntimeException
     */
    protected function manageTimeout()
    {
        $configuration = $this->getConfiguration();
        $timeoutKey = $configuration->getTimeoutKey();

        if (array_key_exists($timeoutKey, $this->data) && $this->data[$timeoutKey] < time()) {
            $this->emit(Event::named('pre.timeout'), $this);

            $this->sessionManager->sessionRegenerateId();

            $this->emit(Event::named('post.timeout'), $this);
        }

        $this->data[$timeoutKey] = time() + $configuration->getLifetime();
    }

    /**
     * Get cookie header content.
     *
     * @return string
     */
    public function getCookieString() : string
    {
        $configuration = $this->getConfiguration();

        $timeoutKey = $configuration->getTimeoutKey();
        $expireTime = array_key_exists($timeoutKey, $this->data)
            ? $this->data[$timeoutKey]
            : time() + $configuration->getLifetime();

        return sprintf(
            '%s=%s; %s',
            urlencode($configuration->getName()),
            urlencode($this->getId()),
            $this->getCookieParameters($expireTime)
        );
    }

    /**
     * Get session cookie parameters.
     *
     * @param int $expireTime
     *
     * @return string
     */
    protected function getCookieParameters(int $expireTime) : string
    {
        $configuration = $this->getConfiguration();

        $cookieParams = [
            sprintf(
                'expires=%s; max-age=%s',
                gmdate('D, d M Y H:i:s T', $expireTime),
                $configuration->getLifetime()
            ),
        ];

        if (!empty($configuration->getCookiePath())) {
            $cookieParams[] = 'path=' . $configuration->getCookiePath();
        }

        $domain = $this->getCookieDomain();
        if (!empty($domain)) {
            $cookieParams[] = 'domain=' . $domain;
        }

        if ($configuration->isCookieSecure()) {
            $cookieParams[] = 'secure';
        }

        if ($configuration->isCookieHttpOnly()) {
            $cookieParams[] = 'httponly';
        }

        return implode('; ', $cookieParams);
    }

    /**
     * Get normalized cookie domain.
     *
     * @return string
     */
    protected function getCookieDomain() : string
    {
        $configuration = $this->getConfiguration();

        $domain = $configuration->getCookieDomain();

        // Current domain for local host names or IP addresses
        if (empty($domain)
            || strpos($domain, '.') === false
            || filter_var($domain, FILTER_VALIDATE_IP) !== false
        ) {
            return '';
        }

        return $domain[0] === '.' ? $domain : '.' . $domain;
    }

    /**
     * Get session configuration.
     *
     * @return Configuration
     */
    protected function getConfiguration() : Configuration
    {
        return $this->sessionManager->getConfiguration();
    }
}
