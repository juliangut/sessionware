<?php

/*
 * sessionware (https://github.com/juliangut/sessionware).
 * PSR7 session management middleware.
 *
 * @license BSD-3-Clause
 * @link https://github.com/juliangut/sessionware
 * @author Julián Gutiérrez <juliangut@gmail.com>
 */

namespace Jgut\Middleware\Sessionware\Tests;

use Jgut\Middleware\Sessionware\Session;
use Jgut\Middleware\Sessionware\SessionStart;
use Jgut\Middleware\Sessionware\Sessionware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequestFactory;

/**
 * Session start middleware test class.
 */
class SessionStartTest extends SessionTestCase
{
    public function testSessionNotStarted()
    {
        $session = $this->getMockBuilder(Session::class)
            ->disableOriginalConstructor()
            ->getMock();
        $session->expects(self::once())
            ->method('start');
        /* @var Session $session */

        $middleware = new SessionStart();

        $request = (ServerRequestFactory::fromGlobals())->withAttribute(Sessionware::SESSION_KEY, $session);
        $callback = function (ServerRequestInterface $request, ResponseInterface $response) {
            return $response;
        };

        $middleware($request, new Response, $callback);
    }
}
