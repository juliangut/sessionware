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

use Jgut\Middleware\Sessionware\Sessionware;
use PHPUnit\Framework\TestCase;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequestFactory;

/**
 * PHP session handler middleware test class.
 */
class SessionwareTest extends TestCase
{
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
        // Set a high probability to launch garbage collector
        ini_set('session.gc_probability', 1);
        ini_set('session.gc_divisor', 4);

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
        }

        $this->request = ServerRequestFactory::fromGlobals();
        $this->response = new Response;
        $this->callback = function ($request, $response) {
            return $response;
        };
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     *
     * @expectedException \RuntimeException
     * @expectedExceptionMessageRegExp /^Session has already been started/
     */
    public function testSessionAlreadyStarted()
    {
        session_id('SessionWareSession');

        session_start();

        $middleware = new Sessionware(['name' => 'SessionWareSession']);

        $middleware($this->request, $this->response, $this->callback);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testSessionTimeoutControlKey()
    {
        $middleware = new Sessionware(['name' => 'SessionWareSession', 'timeoutKey' => '__TIMEOUT__']);

        $middleware($this->request, $this->response, $this->callback);

        $limitTimeout = time() - Sessionware::SESSION_LIFETIME_EXTENDED;
        $_SESSION['__TIMEOUT__'] = $limitTimeout;
        session_write_close();

        $sessionHolder = new \stdClass();
        $middleware->addListener('pre.session_timeout', function ($sessionId) use ($sessionHolder) {
            $sessionHolder->id = $sessionId;
        });

        $assert = $this;
        $middleware->addListener('post.session_timeout', function ($sessionId) use ($assert, $sessionHolder) {
            $assert::assertNotNull($sessionHolder->id);
            $assert::assertNotEquals($sessionHolder->id, $sessionId);
        });

        $middleware($this->request, $this->response, $this->callback);

        self::assertEquals(PHP_SESSION_ACTIVE, session_status());
        self::assertTrue(array_key_exists('__TIMEOUT__', $_SESSION));
        self::assertNotEquals($_SESSION['__TIMEOUT__'], $limitTimeout);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     *
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage "  " is not a valid session timeout
     */
    public function testSessionErrorTimeoutControlKey()
    {
        $middleware = new Sessionware(['name' => 'SessionWareSession', 'timeoutKey' => '  ']);

        $middleware($this->request, $this->response, $this->callback);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     *
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Session name must be a non empty string
     */
    public function testEmptySessionName()
    {
        $middleware = new Sessionware(['name' => '']);

        $middleware($this->request, $this->response, $this->callback);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testSessionName()
    {
        $middleware = new Sessionware(['name' => 'SessionWareSession']);

        /** @var Response $response */
        $response = $middleware($this->request, $this->response, $this->callback);

        self::assertEquals(PHP_SESSION_ACTIVE, session_status());
        self::assertEquals('SessionWareSession', session_name());
        self::assertSame(strpos($response->getHeaderLine('Set-Cookie'), 'SessionWareSession'), 0);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testSessionIdFromFunction()
    {
        $sessionId = Sessionware::generateSessionId();

        session_id($sessionId);

        $middleware = new Sessionware(['name' => 'SessionWareSession']);

        /** @var Response $response */
        $response = $middleware($this->request, $this->response, $this->callback);

        self::assertEquals(PHP_SESSION_ACTIVE, session_status());
        self::assertNotSame(strpos($response->getHeaderLine('Set-Cookie'), $sessionId), false);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testSessionIdFromRequest()
    {
        $sessionId = Sessionware::generateSessionId();

        $request = ServerRequestFactory::fromGlobals(null, null, null, ['SessionWareSession' => $sessionId]);

        $middleware = new Sessionware(['name' => 'SessionWareSession']);

        /** @var Response $response */
        $response = $middleware($request, $this->response, $this->callback);

        self::assertEquals(PHP_SESSION_ACTIVE, session_status());
        self::assertNotSame(strpos($response->getHeaderLine('Set-Cookie'), $sessionId), false);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testGeneratedSessionId()
    {
        $middleware = new Sessionware(['name' => 'SessionWareSession']);

        $middleware($this->request, $this->response, $this->callback);

        self::assertEquals(PHP_SESSION_ACTIVE, session_status());
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testSessionEmptySavePath()
    {
        $middleware = new Sessionware(['name' => 'SessionWareSession', 'savePath' => '']);

        $middleware($this->request, $this->response, $this->callback);

        self::assertEquals(PHP_SESSION_ACTIVE, session_status());
        self::assertTrue(is_dir(sys_get_temp_dir() . '/SessionWareSession'));
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testSessionSavePathFromFunction()
    {
        $tmpPath = sys_get_temp_dir() . '/SessionWareSession';

        session_save_path($tmpPath);

        $middleware = new Sessionware(['name' => 'SessionWareSession']);

        $middleware($this->request, $this->response, $this->callback);

        self::assertEquals(PHP_SESSION_ACTIVE, session_status());
        self::assertTrue(is_dir($tmpPath));
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testSessionSavePathFromParameter()
    {
        $tmpPath = sys_get_temp_dir() . '/SessionWareSession';

        $middleware = new Sessionware(['name' => 'SessionWareSession', 'savePath' => $tmpPath]);

        $middleware($this->request, $this->response, $this->callback);

        self::assertEquals(PHP_SESSION_ACTIVE, session_status());
        self::assertTrue(is_dir($tmpPath));
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     *
     * @expectedException \RuntimeException
     * @expectedExceptionMessageRegExp /^Failed to create session save path/
     */
    public function testSessionErrorSavePath()
    {
        $middleware = new Sessionware(['name' => 'SessionWareSession', 'savePath' => '/my-fake-dir']);

        $middleware($this->request, $this->response, $this->callback);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testSessionTimeoutDefault()
    {
        ini_set('session.cookie_lifetime', 0);
        ini_set('session.gc_maxlifetime', 0);

        $middleware = new Sessionware(['name' => 'SessionWareSession']);

        $middleware($this->request, $this->response, $this->callback);

        self::assertEquals(PHP_SESSION_ACTIVE, session_status());
        self::assertEquals(Sessionware::SESSION_LIFETIME_DEFAULT, ini_get('session.gc_maxlifetime'));
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testSessionTimeoutByCookieLifetime()
    {
        ini_set('session.cookie_lifetime', 10);
        ini_set('session.gc_maxlifetime', Sessionware::SESSION_LIFETIME_EXTENDED);

        $middleware = new Sessionware(['name' => 'SessionWareSession']);

        $middleware($this->request, $this->response, $this->callback);

        self::assertEquals(PHP_SESSION_ACTIVE, session_status());
        self::assertEquals(10, ini_get('session.gc_maxlifetime'));
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testSessionTimeoutByMaxLifetime()
    {
        ini_set('session.cookie_lifetime', 0);
        ini_set('session.gc_maxlifetime', Sessionware::SESSION_LIFETIME_NORMAL);

        $middleware = new Sessionware(['name' => 'SessionWareSession']);

        $middleware($this->request, $this->response, $this->callback);

        self::assertEquals(PHP_SESSION_ACTIVE, session_status());
        self::assertEquals(Sessionware::SESSION_LIFETIME_NORMAL, ini_get('session.gc_maxlifetime'));
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testSessionTimeoutByParameter()
    {
        $middleware = new Sessionware([
            'name' => 'SessionWareSession',
            'lifetime' => Sessionware::SESSION_LIFETIME_SHORT,
        ]);

        $middleware($this->request, $this->response, $this->callback);

        self::assertEquals(PHP_SESSION_ACTIVE, session_status());
        self::assertEquals(Sessionware::SESSION_LIFETIME_SHORT, ini_get('session.gc_maxlifetime'));
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     *
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Session lifetime must be at least 1
     */
    public function testSessionErrorTimeout()
    {
        $middleware = new Sessionware(['name' => 'SessionWareSession', 'lifetime' => 0]);

        $middleware($this->request, $this->response, $this->callback);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testSessionDefaultParams()
    {
        $middleware = new Sessionware(['name' => 'SessionWareSession'], ['parameter' => 'value']);

        $middleware($this->request, $this->response, $this->callback);

        self::assertEquals(PHP_SESSION_ACTIVE, session_status());
        self::assertEquals('value', $_SESSION['parameter']);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testSessionCookieParams()
    {
        $middleware = new Sessionware([
            'name' => 'SessionWareSession',
            'lifetime' => 300,
            'domain' => 'http://example.com',
            'path' => 'path',
            'secure' => true,
            'httponly' => true,
        ]);

        /** @var Response $response */
        $response = $middleware($this->request, $this->response, $this->callback);

        self::assertEquals(PHP_SESSION_ACTIVE, session_status());

        $cookieHeader = $response->getHeaderLine('Set-Cookie');
        self::assertTrue(strpos($cookieHeader, 'SessionWareSession') !== false);
        self::assertTrue(strpos($cookieHeader, 'http://example.com') !== false);
        self::assertTrue(strpos($cookieHeader, 'path') !== false);
        self::assertTrue(strpos($cookieHeader, 'secure') !== false);
        self::assertTrue(strpos($cookieHeader, 'httponly') !== false);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testSessionEndedCookieParams()
    {
        $middleware = new Sessionware([
            'name' => 'SessionWareSession',
            'lifetime' => 300,
            'domain' => 'http://example.com',
        ]);
        $expiration = gmdate('D, d M Y H:i:s T', time() - 300);

        /** @var Response $response */
        $response = $middleware(
            $this->request,
            $this->response,
            function ($request, $response) {
                $_SESSION = [];

                // Serialization is not allowed in PHPUnit running on a separate process
                //session_unset();
                //session_destroy();

                return $response;
            }
        );

        self::assertEquals(PHP_SESSION_ACTIVE, session_status());

        $cookieHeader = $response->getHeaderLine('Set-Cookie');
        self::assertTrue(strpos($cookieHeader, 'SessionWareSession') !== false);
        self::assertTrue(strpos($cookieHeader, 'http://example.com') !== false);
        self::assertTrue(strpos($cookieHeader, $expiration) !== false);
    }
}
