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

namespace Jgut\Sessionware\Tests\Middleware;

use Jgut\Sessionware\Middleware\SessionHandling;
use Jgut\Sessionware\Middleware\SessionStart;
use Jgut\Sessionware\Session;
use Jgut\Sessionware\Tests\SessionTestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequestFactory;

/**
 * Session start middleware test class.
 */
class SessionStartTest extends SessionTestCase
{
    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage SessionStart middleware must be run after SessionHandling middleware
     */
    public function testNoSessionHandlingMiddleware()
    {
        $middleware = new SessionStart();

        $callback = function (ServerRequestInterface $request, ResponseInterface $response) {
            return $response;
        };

        $middleware(ServerRequestFactory::fromGlobals(), new Response, $callback);
    }

    public function testSessionStart()
    {
        $session = $this->getMockBuilder(Session::class)
            ->disableOriginalConstructor()
            ->getMock();
        $session->expects(self::once())
            ->method('start');
        /* @var Session $session */

        $middleware = new SessionStart();

        $request = (ServerRequestFactory::fromGlobals())->withAttribute(SessionHandling::SESSION_KEY, $session);
        $callback = function (ServerRequestInterface $request, ResponseInterface $response) {
            return $response;
        };

        $middleware($request, new Response, $callback);
    }
}
