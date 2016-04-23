<?php
/**
 * SessionWare (https://github.com/juliangut/sessionware)
 * PSR7 session manager middleware
 *
 * @license BSD-3-Clause
 * @author Julián Gutiérrez <juliangut@gmail.com>
 */

namespace Jgut\Middleware\Sessionware\Tests;

use Jgut\Middleware\SessionWare;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequestFactory;

/**
 * PHP session handler middleware test class.
 */
class SessionWareTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Zend\Diactoros\Request
     */
    protected $request;

    /**
     * @var \Zend\Diactoros\Response
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
            session_unset();
            session_destroy();
        }

        // High probability to launch garbage collector
        ini_set('session.gc_probability', 1);
        ini_set('session.gc_divisor', 4);

        $this->request = ServerRequestFactory::fromGlobals();
        $this->response = new Response;
        $this->callback = function ($request, $response) {
            return $response;
        };
    }

    /**
     * @runInSeparateProcess
     *
     * @expectedException \RuntimeException
     * @expectedExceptionMessageRegExp /^Session has already been started/
     */
    public function testSessionAlreadyStarted()
    {
        session_id('SessionWareSession');

        session_start();

        $middleware = new SessionWare(['name' => 'SessionWareSession']);

        $middleware($this->request, $this->response, $this->callback);
    }

    /**
     * @runInSeparateProcess
     */
    public function testSessionTimeoutControlKey()
    {
        $middleware = new SessionWare(['name' => 'SessionWareSession', 'timeoutKey' => '__TIMEOUT__']);

        $middleware($this->request, $this->response, $this->callback);

        self::assertEquals(PHP_SESSION_ACTIVE, session_status());
        self::assertTrue(array_key_exists('__TIMEOUT__', $_SESSION));
    }

    /**
     * @runInSeparateProcess
     *
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage "  " is not a valid session timeout
     */
    public function testSessionErrorTimeoutControlKey()
    {
        $middleware = new SessionWare(['name' => 'SessionWareSession', 'timeoutKey' => '  ']);

        $middleware($this->request, $this->response, $this->callback);
    }

    /**
     * @runInSeparateProcess
     */
    public function testSessionName()
    {
        $middleware = new SessionWare(['name' => 'SessionWareSession']);

        /** @var \Zend\Diactoros\Request $response */
        $response = $middleware($this->request, $this->response, $this->callback);

        self::assertEquals(PHP_SESSION_ACTIVE, session_status());
        self::assertEquals('SessionWareSession', session_name());
        self::assertSame(strpos($response->getHeaderLine('Set-Cookie'), 'SessionWareSession'), 0);
    }

    /**
     * @runInSeparateProcess
     */
    public function testSessionIdFromFunction()
    {
        session_id('madeUpSessionId');

        $middleware = new SessionWare(['name' => 'SessionWareSession']);

        $middleware($this->request, $this->response, $this->callback);

        self::assertEquals(PHP_SESSION_ACTIVE, session_status());
        self::assertEquals('madeUpSessionId', session_id());
    }

    /**
     * @runInSeparateProcess
     */
    public function testSessionIdFromRequest()
    {
        $request = ServerRequestFactory::fromGlobals(null, null, null, ['SessionWareSession' => 'madeUpSessionId']);

        $middleware = new SessionWare(['name' => 'SessionWareSession']);

        /** @var \Zend\Diactoros\Request $response */
        $response = $middleware($request, $this->response, $this->callback);

        self::assertEquals(PHP_SESSION_ACTIVE, session_status());
        self::assertEquals('madeUpSessionId', session_id());
        self::assertNotSame(strpos($response->getHeaderLine('Set-Cookie'), 'madeUpSessionId'), false);
    }

    /**
     * @runInSeparateProcess
     */
    public function testSessionSavePathFromFunction()
    {
        $tmpPath = sys_get_temp_dir() . '/SessionWareSession';

        session_save_path($tmpPath);

        $middleware = new SessionWare(['name' => 'SessionWareSession']);

        $middleware($this->request, $this->response, $this->callback);

        self::assertEquals(PHP_SESSION_ACTIVE, session_status());
        self::assertTrue(is_dir($tmpPath));
    }

    /**
     * @runInSeparateProcess
     */
    public function testSessionSavePathFromParameter()
    {
        $tmpPath = sys_get_temp_dir() . '/SessionWareSession';

        $middleware = new SessionWare(['name' => 'SessionWareSession', 'savePath' => $tmpPath]);

        $middleware($this->request, $this->response, $this->callback);

        self::assertEquals(PHP_SESSION_ACTIVE, session_status());
        self::assertTrue(is_dir($tmpPath));
    }

    /**
     * @runInSeparateProcess
     *
     * @expectedException \RuntimeException
     * @expectedExceptionMessageRegExp /^Failed to create session save path/
     */
    public function testSessionErrorSavePath()
    {
        $middleware = new SessionWare(['name' => 'SessionWareSession', 'savePath' => '/my-fake-dir']);

        $middleware($this->request, $this->response, $this->callback);
    }

    /**
     * @runInSeparateProcess
     */
    public function testSessionTimeoutDefault()
    {
        ini_set('session.cookie_lifetime', 0);
        ini_set('session.gc_maxlifetime', 0);

        $middleware = new SessionWare(['name' => 'SessionWareSession']);

        $middleware($this->request, $this->response, $this->callback);

        self::assertEquals(PHP_SESSION_ACTIVE, session_status());
        self::assertEquals(SessionWare::SESSION_LIFETIME_DEFAULT, ini_get('session.gc_maxlifetime'));
    }

    /**
     * @runInSeparateProcess
     */
    public function testSessionTimeoutByCookieLifetime()
    {
        ini_set('session.cookie_lifetime', 10);
        ini_set('session.gc_maxlifetime', SessionWare::SESSION_LIFETIME_EXTENDED);

        $middleware = new SessionWare(['name' => 'SessionWareSession']);

        $middleware($this->request, $this->response, $this->callback);

        self::assertEquals(PHP_SESSION_ACTIVE, session_status());
        self::assertEquals(10, ini_get('session.gc_maxlifetime'));
    }

    /**
     * @runInSeparateProcess
     */
    public function testSessionTimeoutByMaxLifetime()
    {
        ini_set('session.cookie_lifetime', 0);
        ini_set('session.gc_maxlifetime', SessionWare::SESSION_LIFETIME_NORMAL);

        $middleware = new SessionWare(['name' => 'SessionWareSession']);

        $middleware($this->request, $this->response, $this->callback);

        self::assertEquals(PHP_SESSION_ACTIVE, session_status());
        self::assertEquals(SessionWare::SESSION_LIFETIME_NORMAL, ini_get('session.gc_maxlifetime'));
    }

    /**
     * @runInSeparateProcess
     */
    public function testSessionTimeoutByParameter()
    {
        $middleware = new SessionWare([
            'name' => 'SessionWareSession',
            'lifetime' => SessionWare::SESSION_LIFETIME_SHORT,
        ]);

        $middleware($this->request, $this->response, $this->callback);

        self::assertEquals(PHP_SESSION_ACTIVE, session_status());
        self::assertEquals(SessionWare::SESSION_LIFETIME_SHORT, ini_get('session.gc_maxlifetime'));
    }

    /**
     * @runInSeparateProcess
     *
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage "0" is not a valid session lifetime
     */
    public function testSessionErrorTimeout()
    {
        $middleware = new SessionWare(['name' => 'SessionWareSession', 'lifetime' => 0]);

        $middleware($this->request, $this->response, $this->callback);
    }

    /**
     * @runInSeparateProcess
     */
    public function testSessionDefaultParams()
    {
        $middleware = new SessionWare(['name' => 'SessionWareSession'], ['parameter' => 'value']);

        $middleware($this->request, $this->response, $this->callback);

        self::assertEquals(PHP_SESSION_ACTIVE, session_status());
        self::assertEquals('value', $_SESSION['parameter']);
    }

    /**
     * @runInSeparateProcess
     */
    public function testSessionCookieParams()
    {
        $middleware = new SessionWare([
            'name' => 'SessionWareSession',
            'lifetime' => 300,
            'domain' => 'http://example.com',
            'path' => 'path',
            'secure' => true,
            'httponly' => true,
        ]);

        /** @var \Zend\Diactoros\Request $response */
        $response = $middleware($this->request, $this->response, $this->callback);

        self::assertEquals(PHP_SESSION_ACTIVE, session_status());

        $cookieHeader = $response->getHeaderLine('Set-Cookie');
        self::assertTrue(strpos($cookieHeader, 'SessionWare') !== false);
        self::assertTrue(strpos($cookieHeader, 'http://example.com') !== false);
        self::assertTrue(strpos($cookieHeader, 'path') !== false);
        self::assertTrue(strpos($cookieHeader, 'secure') !== false);
        self::assertTrue(strpos($cookieHeader, 'httponly') !== false);
    }
}
