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

use Jgut\Middleware\Sessionware\Configuration;
use Jgut\Middleware\Sessionware\Handler\Dummy;
use Jgut\Middleware\Sessionware\Manager\Native;
use Jgut\Middleware\Sessionware\Session;
use Jgut\Middleware\Sessionware\Sessionware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequestFactory;

/**
 * PHP session handler middleware t_est class.
 */
class SessionwareTest extends SessionTestCase
{
    /**
     * @var Configuration
     */
    protected $configuration;

    /**
     * @var \Psr\Http\Message\ServerRequestInterface
     */
    protected $request;

    /**
     * @var \Psr\Http\Message\ResponseInterface
     */
    protected $response;

    /**
     * {@inheritdoc}
     */
    public function setUp()
    {
        parent::setUp();

        $configuration = $this->getMockBuilder(Configuration::class)
            ->disableOriginalConstructor()
            ->getMock();
        $configuration
            ->expects(self::any())
            ->method('getName')
            ->will(self::returnValue('Sessionware'));
        $configuration
            ->expects(self::any())
            ->method('getLifetime')
            ->will(self::returnValue(Configuration::LIFETIME_EXTENDED));
        $configuration
            ->expects(self::any())
            ->method('getTimeoutKey')
            ->will(self::returnValue(Configuration::TIMEOUT_KEY_DEFAULT));
        $configuration
            ->expects(self::any())
            ->method('getCookiePath')
            ->will(self::returnValue('/'));
        $configuration
            ->expects(self::any())
            ->method('getCookieDomain')
            ->will(self::returnValue('http://example.com'));
        $configuration
            ->expects(self::any())
            ->method('isCookieSecure')
            ->will(self::returnValue(true));
        $configuration
            ->expects(self::any())
            ->method('isCookieHttpOnly')
            ->will(self::returnValue(true));
        /* @var Configuration $configuration */

        $this->configuration = $configuration;
        $this->request = ServerRequestFactory::fromGlobals();
        $this->response = new Response;
    }

    /**
     * @runInSeparateProcess
     */
    public function testSessionNotStarted()
    {
        $manager = $this->getMockBuilder(Native::class)
            ->disableOriginalConstructor()
            ->getMock();
        $manager
            ->expects(self::any())
            ->method('isSessionStarted')
            ->will(self::returnValue(false));
        $manager
            ->expects(self::any())
            ->method('getConfiguration')
            ->will(self::returnValue($this->configuration));
        /* @var \Jgut\Middleware\Sessionware\Manager\Manager $manager */

        $middleware = new Sessionware($manager);

        $assert = $this;
        $callback = function (ServerRequestInterface $request, ResponseInterface $response) use ($assert) {
            $assert::assertInstanceOf(Session::class, Sessionware::getSession($request));

            return $response;
        };
        /* @var Response $response */
        $response = $middleware($this->request, $this->response, $callback);

        self::assertEmpty($response->getHeaderLine('Set-Cookie'));
    }

    /**
     * @runInSeparateProcess
     */
    public function testSessionCookie()
    {
        $manager = new Native($this->configuration, new Dummy());

        $middleware = new Sessionware($manager);

        $sessionId = 'ch3OZUQU3J93jqFRlbC7t5zzUrXq1m8AmBj87wdaUNZMzKHb9T5sYd8iZItWFR720NfoYmAztV3Izbpt';
        $request = ServerRequestFactory::fromGlobals(
            null,
            null,
            null,
            [$this->configuration->getName() => $sessionId]
        );
        $assert = $this;
        $callback = function (ServerRequestInterface $request, ResponseInterface $response) use ($assert, &$sessionId) {
            $session = Sessionware::getSession($request);

            $assert::assertInstanceOf(Session::class, $session);

            $session->start();

            $assert::assertEquals($sessionId, $session->getId());

            $session->regenerateId();

            $sessionId = $session->getId();

            return $response;
        };

        /* @var Response $response */
        $response = $middleware($request, $this->response, $callback);

        $cookieHeader = $response->getHeaderLine('Set-Cookie');

        self::assertSame(strpos($cookieHeader, $this->configuration->getName() . '=' . $sessionId), 0);
        self::assertNotSame(strpos($cookieHeader, 'max-age=' . $this->configuration->getLifetime()), false);
        self::assertNotSame(strpos($cookieHeader, 'path=' . $this->configuration->getCookiePath()), false);
        self::assertNotSame(strpos($cookieHeader, 'domain=' . $this->configuration->getCookieDomain()), false);
        self::assertNotSame(strpos($cookieHeader, 'secure'), false);
        self::assertNotSame(strpos($cookieHeader, 'httponly'), false);
    }
}
