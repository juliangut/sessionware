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

namespace Jgut\Sessionware\Middleware;

use Jgut\Sessionware\Manager\Manager;
use Jgut\Sessionware\Session;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Session handling middleware.
 */
class SessionHandling
{
    const SESSION_KEY = '__SESSIONWARE_SESSION__';

    /**
     * @var Session
     */
    protected $session;

    /**
     * Middleware constructor.
     *
     * @param Session $session
     */
    public function __construct(Session $session)
    {
        $this->session = $session;
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
    ) : ResponseInterface {
        $requestCookies = $request->getCookieParams();
        $sessionName = $this->session->getManager()->getConfiguration()->getName();
        if (array_key_exists($sessionName, $requestCookies) && !empty($requestCookies[$sessionName])) {
            $this->session->setId($requestCookies[$sessionName]);
        }

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

        return $response->withAddedHeader('Set-Cookie', $this->session->getCookieString());
    }
}
