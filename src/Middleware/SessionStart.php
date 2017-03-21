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
 * Session start middleware.
 */
class SessionStart
{
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
    ): ResponseInterface {
        $session = SessionHandling::getSession($request);

        if (!$session instanceof Session) {
            throw new \RuntimeException('SessionStart middleware must be run after SessionHandling middleware');
        }

        $session->start();

        return $next($request, $response);
    }
}
