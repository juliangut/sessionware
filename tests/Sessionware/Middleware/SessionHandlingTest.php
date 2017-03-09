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

use Jgut\Sessionware\Configuration;
use Jgut\Sessionware\Handler\Memory;
use Jgut\Sessionware\Manager\Native;
use Jgut\Sessionware\Middleware\SessionHandling;
use Jgut\Sessionware\Session;
use Jgut\Sessionware\Tests\SessionTestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequestFactory;

/**
 * Session handling middleware test class.
 */
class SessionHandlingTest extends SessionTestCase
{
    /**
     * @var Configuration
     */
    protected $configuration;

    /**
     * @var ServerRequestInterface
     */
    protected $request;

    /**
     * @var ResponseInterface
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
            ->will(self::returnValue('.example.com'));
        $configuration
            ->expects(self::any())
            ->method('isCookieSecure')
            ->will(self::returnValue(true));
        $configuration
            ->expects(self::any())
            ->method('isCookieHttpOnly')
            ->will(self::returnValue(true));
        $configuration
            ->expects(self::any())
            ->method('getCookieSameSite')
            ->will(self::returnValue('Strict'));
        /* @var Configuration $configuration */

        $this->configuration = $configuration;
        $this->request = ServerRequestFactory::fromGlobals();
        $this->response = new Response;
    }

    public function testSessionNotStarted()
    {
        $manager = $this->getMockBuilder(Native::class)
            ->disableOriginalConstructor()
            ->getMock();
        $manager
            ->expects(self::any())
            ->method('isStarted')
            ->will(self::returnValue(false));
        $manager
            ->expects(self::any())
            ->method('getConfiguration')
            ->will(self::returnValue($this->configuration));
        /* @var \Jgut\Sessionware\Manager\Manager $manager */

        $session = $this->getMockBuilder(Session::class)
            ->disableOriginalConstructor()
            ->getMock();
        $session
            ->expects(self::any())
            ->method('getManager')
            ->will(self::returnValue($manager));
        /* @var Session $session */

        $middleware = new SessionHandling($session);

        $assert = $this;
        $callback = function (ServerRequestInterface $request, ResponseInterface $response) use ($assert) {
            $assert::assertInstanceOf(Session::class, SessionHandling::getSession($request));

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
        $session = new Session(new Native($this->configuration, new Memory()));

        $middleware = new SessionHandling($session);

        $sessionId = 'ch3OZUQU3J93jqFRlbC7t5zzUrXq1m8AmBj87wdaUNZMzKHb9T5sYd8iZItWFR720NfoYmAztV3Izbpt';
        $request = ServerRequestFactory::fromGlobals(
            null,
            null,
            null,
            [$this->configuration->getName() => $sessionId]
        );
        $callback = function (ServerRequestInterface $request, ResponseInterface $response) {
            SessionHandling::getSession($request)->start();

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
        self::assertNotSame(strpos($cookieHeader, 'SameSite=' . $this->configuration->getCookieSameSite()), false);
    }
}
