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

namespace Jgut\Middleware\Sessionware;

use Jgut\Middleware\Sessionware\Manager\Manager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Session handling middleware.
 */
class Sessionware
{
    const SESSION_KEY = '__SESSIONWARE_SESSION__';

    /**
     * @var Manager
     */
    protected $sessionManager;

    /**
     * @var Session
     */
    protected $session;

    /**
     * Middleware constructor.
     *
     * @param Manager $sessionManager
     */
    public function __construct(Manager $sessionManager)
    {
        $this->sessionManager = $sessionManager;
    }

    /**
     * Get session from request.
     *
     * @param ServerRequestInterface $request
     *
     * @return Session
     */
    public static function getSession(ServerRequestInterface $request) : Session
    {
        return $request->getAttribute(static::SESSION_KEY);
    }

    /**
     * Execute middleware.
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @param callable               $next
     *
     * @throws \RuntimeException
     *
     * @return ResponseInterface
     */
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        callable $next
    ) :ResponseInterface {
        $requestCookies = $request->getCookieParams();
        $sessionName = $this->sessionManager->getConfiguration()->getName();
        if (array_key_exists($sessionName, $requestCookies) && !empty($requestCookies[$sessionName])) {
            $this->sessionManager->setSessionId($requestCookies[$sessionName]);
        }

        $this->session = new Session($this->sessionManager);

        $response = $next($request->withAttribute(static::SESSION_KEY, $this->session), $response);

        $response = $this->respondWithSessionCookie($response);

        $this->session->close();

        return $response;
    }

    /**
     * Add session cookie Set-Cookie header to response.
     *
     * @param ResponseInterface $response
     *
     * @return ResponseInterface
     */
    protected function respondWithSessionCookie(ResponseInterface $response) : ResponseInterface
    {
        if (!$this->session->isActive()) {
            return $response;
        }

        $configuration = $this->getConfiguration();

        $timeoutKey = $configuration->getTimeoutKey();
        $expireTime = $this->session->has($timeoutKey)
            ? $this->session->get($timeoutKey)
            : time() - $configuration->getLifetime();

        return $response->withAddedHeader(
            'Set-Cookie',
            sprintf(
                '%s=%s; %s',
                urlencode($configuration->getName()),
                urlencode($this->session->getId()),
                $this->getSessionCookieParameters($expireTime)
            )
        );
    }

    /**
     * Get session cookie parameters.
     *
     * @param int $expireTime
     *
     * @return string
     */
    protected function getSessionCookieParameters(int $expireTime) : string
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

        if (!empty($configuration->getCookieDomain())) {
            $cookieParams[] = 'domain=' . $configuration->getCookieDomain();
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
     * Get session configuration.
     *
     * @return Configuration
     */
    protected function getConfiguration() : Configuration
    {
        return $this->sessionManager->getConfiguration();
    }
}
