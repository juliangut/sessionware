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
use Psr\Http\Message\ServerRequestInterface;

/**
 * Session helper.
 *
 * @SuppressWarnings(PMD.TooManyPublicMethods)
 */
class Session implements EmitterAwareInterface
{
    use EmitterTrait;

    /**
     * Session configuration.
     *
     * @var Configuration
     */
    protected $configuration;

    /**
     * Session manager.
     *
     * @var Manager
     */
    protected $sessionManager;

    /**
     * Session initial data.
     *
     * @var Collection
     */
    protected $originalData;

    /**
     * Session data.
     *
     * @var Collection
     */
    protected $data;

    /**
     * Session shutdown method is registered.
     *
     * @var bool
     */
    protected $shutdownRegistered = false;

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
        $this->configuration = $sessionManager->getConfiguration();

        $this->data = new Collection($initialData);
        $this->originalData = clone $this->data;
    }

    /**
     * Has session been started.
     *
     * @return bool
     */
    public function isActive() : bool
    {
        return $this->sessionManager->isStarted();
    }

    /**
     * Has session been destroyed.
     *
     * @return bool
     */
    public function isDestroyed() : bool
    {
        return $this->sessionManager->isDestroyed();
    }

    /**
     * Get session identifier.
     *
     * @return string
     */
    public function getId() : string
    {
        return $this->sessionManager->getId();
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

        $this->sessionManager->setId($sessionId);
    }

    /**
     * Load session identifier from request.
     *
     * @param ServerRequestInterface $request
     *
     * @throws \RuntimeException
     */
    public function loadIdFromRequest(ServerRequestInterface $request)
    {
        $requestCookies = $request->getCookieParams();
        $sessionName = $this->configuration->getName();

        if (array_key_exists($sessionName, $requestCookies) && !empty($requestCookies[$sessionName])) {
            $this->setId($requestCookies[$sessionName]);
        }
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

        if (!$this->shutdownRegistered) {
            register_shutdown_function([$this, 'close']);

            $this->shutdownRegistered = true;
        }

        $this->emit(Event::named('preStart'), $this);

        foreach ($this->sessionManager->start() as $key => $value) {
            $this->set($key, $value);
        }
        $this->originalData = clone $this->data;

        if ($this->sessionManager->shouldRegenerateId()) {
            $this->regenerateId();
        }

        $this->emit(Event::named('postStart'), $this);

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

        $this->emit(Event::named('preRegenerateId'), $this);

        $this->sessionManager->regenerateId();

        $this->emit(Event::named('postRegenerateId'), $this);
    }

    /**
     * Revert session to its original data.
     */
    public function reset()
    {
        if (!$this->isActive()) {
            return;
        }

        $this->emit(Event::named('preReset'), $this);

        $this->data = clone $this->originalData;

        $this->emit(Event::named('postReset'), $this);
    }

    /**
     * Close session keeping original session data.
     */
    public function abort()
    {
        if (!$this->isActive()) {
            return;
        }

        $this->emit(Event::named('preAbort'), $this);

        $this->sessionManager->close($this->originalData->getAll());

        $this->emit(Event::named('postAbort'), $this);
    }

    /**
     * Close session.
     */
    public function close()
    {
        if (!$this->isActive()) {
            return;
        }

        $this->emit(Event::named('preClose'), $this);

        $this->sessionManager->close($this->data->getAll());

        $this->emit(Event::named('postClose'), $this);
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

        $this->emit(Event::named('preDestroy'), $this);

        $this->sessionManager->destroy();

        $this->emit(Event::named('postDestroy'), $this);

        $this->originalData = new Collection();
        $this->data = new Collection();
    }

    /**
     * Manage session timeout.
     *
     * @throws \RuntimeException
     */
    protected function manageTimeout()
    {
        $timeoutKey = $this->configuration->getTimeoutKey();
        $sessionTimeout = $this->data->get($timeoutKey);

        if ($sessionTimeout && $sessionTimeout < time()) {
            $this->emit(Event::named('preTimeout'), $this);

            $this->sessionManager->regenerateId();

            $this->emit(Event::named('postTimeout'), $this);
        }

        $this->data->set($timeoutKey, time() + $this->configuration->getLifetime());
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
        return $this->data->has($key);
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
        return $this->data->get($key, $default);
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
        $this->data->set($key, $value);

        return $this;
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
        $this->data->remove($key);

        return $this;
    }

    /**
     * Remove all session parameters.
     *
     * @return self
     */
    public function clear() : self
    {
        $timeoutKey = $this->configuration->getTimeoutKey();
        $sessionTimeout = $this->data->get($timeoutKey, time() + $this->configuration->getLifetime());

        $this->data->clear();
        $this->data->set($timeoutKey, $sessionTimeout);

        return $this;
    }

    /**
     * Get session cookie content.
     *
     * @return string
     */
    public function getSessionCookieString() : string
    {
        if (empty($this->getId())) {
            return '';
        }

        if ($this->isDestroyed()) {
            return $this->getExpiredCookieString();
        }

        return $this->getInitiatedCookieString();
    }

    /**
     * Get session expired cookie.
     *
     * @return string
     */
    protected function getExpiredCookieString() : string
    {
        return sprintf(
            '%s=%s; %s',
            urlencode($this->configuration->getName()),
            urlencode($this->getId()),
            $this->getCookieParameters(0, 1)
        );
    }

    /**
     * Get normal session cookie.
     *
     * @return string
     */
    protected function getInitiatedCookieString() : string
    {
        $lifetime = $this->configuration->getLifetime();
        $timeoutKey = $this->configuration->getTimeoutKey();
        $expireTime = $this->data->get($timeoutKey, time() + $lifetime);

        return sprintf(
            '%s=%s; %s',
            urlencode($this->configuration->getName()),
            urlencode($this->getId()),
            $this->getCookieParameters($expireTime, $lifetime)
        );
    }

    /**
     * Get session cookie parameters.
     *
     * @param int $expireTime
     * @param int $lifetime
     *
     * @return string
     */
    protected function getCookieParameters(int $expireTime, int $lifetime) : string
    {
        $cookieParams = [
            sprintf('expires=%s; max-age=%s', gmdate('D, d M Y H:i:s T', $expireTime), $lifetime),
        ];

        if (!empty($this->configuration->getCookiePath())) {
            $cookieParams[] = 'path=' . $this->configuration->getCookiePath();
        }

        $domain = $this->getCookieDomain();
        if (!empty($domain)) {
            $cookieParams[] = 'domain=' . $domain;
        }

        if ($this->configuration->isCookieSecure()) {
            $cookieParams[] = 'secure';
        }

        if ($this->configuration->isCookieHttpOnly()) {
            $cookieParams[] = 'httponly';
        }

        $cookieParams[] = 'SameSite=' . $this->configuration->getCookieSameSite();

        return implode('; ', $cookieParams);
    }

    /**
     * Get normalized cookie domain.
     *
     * @return string
     */
    protected function getCookieDomain() : string
    {
        $domain = $this->configuration->getCookieDomain();

        // Current domain for local host names or IP addresses
        if (empty($domain)
            || strpos($domain, '.') === false
            || filter_var($domain, FILTER_VALIDATE_IP) !== false
        ) {
            return '';
        }

        return $domain[0] === '.' ? $domain : '.' . $domain;
    }
}
