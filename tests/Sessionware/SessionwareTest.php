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
use Jgut\Middleware\Sessionware\Sessionware;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\TestCase;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequestFactory;

/**
 * PHP session handler middleware t_est class.
 */
class SessionwareTest extends TestCase
{
    /**
     * @var Configuration
     */
    protected $configuration;

    /**
     * @var vfsStreamDirectory
     */
    protected $basePath;

    /**
     * @var \Psr\Http\Message\ServerRequestInterface
     */
    protected $request;

    /**
     * @var \Psr\Http\Message\ResponseInterface
     */
    protected $response;

    /**
     * @var callable
     */
    protected $callback;

    /**
     * {@inheritdoc}
     */
    public function setUp()
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_unset();
            session_destroy();
        }

        // Set a high probability to launch garbage collector
        ini_set('session.gc_probability', 1);
        ini_set('session.gc_divisor', 4);

        session_id('00000000000000000000000000000000');

        $this->basePath = vfsStream::setup('vfsRoot');
        mkdir($this->basePath->url() . '/Sessionware', 0775);

        $this->configuration = new Configuration([
            'name' => 'Sessionware',
            'savePath' => sys_get_temp_dir(), // $this->basePath->url(),
            'getTimeoutKey' => '__timeout__',
        ]);

        $this->request = ServerRequestFactory::fromGlobals();
        $this->response = new Response;
        $this->callback = function ($request, $response) {
            session_start();

            $_SESSION['test'] = 'value';

            return $response;
        };
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     *
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Session has already been started. Check "session.auto_start" ini setting
     */
    public function testSessionAlreadyStarted()
    {
        $middleware = new Sessionware();

        ini_set('session.save_path', $this->configuration->getSavePath() . '/' . $this->configuration->getName());

        session_start();

        $middleware($this->request, $this->response, $this->callback);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testSessionNotStarted()
    {
        $middleware = new Sessionware($this->configuration);

        $callback = function ($request, $response) {
            return $response;
        };
        /* @var Response $response */
        $response = $middleware($this->request, $this->response, $callback);

        self::assertEquals(PHP_SESSION_NONE, session_status());
        self::assertEmpty($response->getHeaderLine('Set-Cookie'));
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testSessionName()
    {
        $middleware = new Sessionware($this->configuration);

        /* @var Response $response */
        $response = $middleware($this->request, $this->response, $this->callback);

        self::assertEquals($this->configuration->getName(), session_name());
        self::assertSame(strpos($response->getHeaderLine('Set-Cookie'), $this->configuration->getName()), 0);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testSessionSavePathDefault()
    {
        $tmpPath = sys_get_temp_dir() . '/' . $this->configuration->getName();
        ini_set('session.save_path', $tmpPath);

        $middleware = new Sessionware($this->configuration);

        $middleware($this->request, $this->response, $this->callback);

        self::assertTrue(is_dir($tmpPath));
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testSessionSavePathCreation()
    {
        $tmpPath = sys_get_temp_dir() . '/' . $this->configuration->getName();
        $middleware = new Sessionware($this->configuration);

        $middleware($this->request, $this->response, $this->callback);

        self::assertTrue(is_dir($tmpPath));
    }

    /**
     * @runInSeparateProcess
     *
     * @expectedException \RuntimeException
     * @expectedExceptionMessageRegExp /^Failed to create session save path ".+", directory might be write protected$/
     */
    public function testInvalidSessionSavePath()
    {
        mkdir($this->basePath->url() . '/writeProtected');
        chmod($this->basePath->url() . '/writeProtected', 0444);

        $this->configuration->setSavePath($this->basePath->url() . '/writeProtected');
        $middleware = new Sessionware($this->configuration);

        $middleware($this->request, $this->response, $this->callback);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testSessionIdFromEnvironment()
    {
        $middleware = new Sessionware($this->configuration);

        $sessionId = substr(
            preg_replace('/[^a-zA-Z0-9-]+/', '', base64_encode(random_bytes(Configuration::SESSION_ID_LENGTH))),
            0,
            Configuration::SESSION_ID_LENGTH
        );
        session_id($sessionId);

        /* @var Response $response */
        $response = $middleware($this->request, $this->response, $this->callback);

        self::assertEquals($this->configuration->getName(), session_name());
        self::assertNotSame(strpos($response->getHeaderLine('Set-Cookie'), $sessionId), false);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testSessionIdFromRequest()
    {
        $middleware = new Sessionware($this->configuration);

        $sessionId = substr(
            preg_replace('/[^a-zA-Z0-9-]+/', '', base64_encode(random_bytes(Configuration::SESSION_ID_LENGTH))),
            0,
            Configuration::SESSION_ID_LENGTH
        );
        $request = ServerRequestFactory::fromGlobals(
            null,
            null,
            null,
            [$this->configuration->getName() => $sessionId]
        );

        /* @var Response $response */
        $response = $middleware($request, $this->response, $this->callback);

        self::assertEquals($this->configuration->getName(), session_name());
        self::assertNotSame(strpos($response->getHeaderLine('Set-Cookie'), $sessionId), false);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testSessionCookieParams()
    {
        $this->configuration->setLifetime(Configuration::LIFETIME_FLASH);
        $this->configuration->setCookiePath('/path');
        $this->configuration->setCookieDomain('example.com');
        $this->configuration->setCookieSecure(true);
        $this->configuration->setCookieHttpOnly(true);

        $middleware = new Sessionware($this->configuration);

        $timeoutKey = $this->configuration->getTimeoutKey();
        $callback = function ($request, $response) use ($timeoutKey) {
            session_start();

            $_SESSION[$timeoutKey] = time() + 1000;

            return $response;
        };

        /* @var Response $response */
        $response = $middleware($this->request, $this->response, $callback);

        $cookieHeader = $response->getHeaderLine('Set-Cookie');

        self::assertNotSame(strpos($cookieHeader, $this->configuration->getName()), false);
        self::assertNotSame(strpos($cookieHeader, 'max-age=' . $this->configuration->getLifetime()), false);
        self::assertNotSame(strpos($cookieHeader, 'path=' . $this->configuration->getCookiePath()), false);
        self::assertNotSame(strpos($cookieHeader, 'domain=' . $this->configuration->getCookieDomain()), false);
        self::assertNotSame(strpos($cookieHeader, 'secure'), false);
        self::assertNotSame(strpos($cookieHeader, 'httponly'), false);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function doTestSessionEndedCookieParams()
    {
        $this->configuration->setLifetime(Configuration::LIFETIME_FLASH);
        $this->configuration->setCookieDomain('example.com');

        $middleware = new Sessionware($this->configuration);
        $expiration = gmdate('D, d M Y H:i:s T', time() - 300);

        $callback = function ($request, $response) {
            return $response;
        };

        /* @var Response $response */
        $response = $middleware($this->request, $this->response, $callback);

        $cookieHeader = $response->getHeaderLine('Set-Cookie');
        self::assertNotSame(strpos($cookieHeader, $this->configuration->getName()), false);
        self::assertNotSame(strpos($cookieHeader, 'max-age=' . $this->configuration->getLifetime()), false);
        self::assertNotSame(strpos($cookieHeader, 'domain=' . $this->configuration->getCookieDomain()), false);
        self::assertNotSame(strpos($cookieHeader, $expiration), false);
    }
}
