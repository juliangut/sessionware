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

namespace Jgut\Sessionware\Middleware;

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
     * @return Session|null
     */
    public static function getSession(ServerRequestInterface $request)
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
        $this->session->loadIdFromRequest($request);

        /* @var ResponseInterface $response */
        $response = $next($request->withAttribute(static::SESSION_KEY, $this->session), $response);

        $this->session->close();

        return $this->withSessionCookie($response);
    }

    /**
     * Return response with added session cookie.
     *
     * @param ResponseInterface $response
     *
     * @return ResponseInterface
     */
    protected function withSessionCookie(ResponseInterface $response) : ResponseInterface
    {
        $cookieString = $this->session->getSessionCookieString();

        if (!empty($cookieString)) {
            $response = $response->withAddedHeader('Set-Cookie', $cookieString);
        }

        return $response;
    }
}
