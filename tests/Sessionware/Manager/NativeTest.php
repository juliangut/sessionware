<?php

/*
 * sessionware (https://github.com/juliangut/sessionware).
 * PSR7 session management middleware.
 *
 * @license BSD-3-Clause
 * @link https://github.com/juliangut/sessionware
 * @author Julián Gutiérrez <juliangut@gmail.com>
 */

namespace Jgut\Middleware\Sessionware\Tests\Manager;

use Jgut\Middleware\Sessionware\Configuration;
use Jgut\Middleware\Sessionware\Handler\Dummy;
use Jgut\Middleware\Sessionware\Manager\Native;
use Jgut\Middleware\Sessionware\Tests\SessionTestCase;
use Jgut\Middleware\Sessionware\Tests\Stubs\MemoryHandlerStub;

/**
 * Native PHP session handler test class.
 */
class NativeTest extends SessionTestCase
{
    /**
     * @var Configuration
     */
    protected $configuration;

    /**
     * @var \Jgut\Middleware\Sessionware\Handler\Handler
     */
    protected $handler;

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
            ->method('getSavePath')
            ->will(self::returnValue(sys_get_temp_dir()));
        $configuration
            ->expects(self::any())
            ->method('getLifetime')
            ->will(self::returnValue(Configuration::LIFETIME_DEFAULT));
        /* @var Configuration $configuration */

        $this->configuration = $configuration;
        $this->handler = new Dummy();
    }

    /**
     * @runInSeparateProcess
     *
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Session has already been started. Check "session.auto_start" ini setting
     */
    public function testSessionAlreadyActive()
    {
        session_start();

        new Native($this->configuration);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage "session.use_trans_sid" ini setting must be set to false
     */
    public function testInvalidSessionUseTransSid()
    {
        ini_set('session.use_trans_sid', true);

        new Native($this->configuration);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage "session.use_cookies" ini setting must be set to true
     */
    public function testInvalidSessionUseCookies()
    {
        ini_set('session.use_cookies', false);

        new Native($this->configuration);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage "session.use_only_cookies" ini setting must be set to true
     */
    public function testInvalidSessionUseOnlyCookies()
    {
        ini_set('session.use_only_cookies', false);

        new Native($this->configuration);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage "session.use_strict_mode" ini setting must be set to false
     */
    public function testInvalidSessionUseStrictMode()
    {
        ini_set('session.use_strict_mode', true);

        new Native($this->configuration);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage "session.cache_limiter" ini setting must be set to empty string
     */
    public function testInvalidSessionCacheLimiter()
    {
        ini_set('session.cache_limiter', 'nocache');

        new Native($this->configuration);
    }

    public function testCreation()
    {
        new Native($this->configuration);

        self::assertEquals('php_serialize', ini_get('session.serialize_handler'));
        self::assertEquals($this->configuration->getLifetime(), ini_get('session.gc_maxlifetime'));
        self::assertEquals('user', ini_get('session.save_handler'));
    }

    public function testGettersSetters()
    {
        $manager = new Native($this->configuration, $this->handler);

        self::assertFalse($manager->isSessionStarted());
        self::assertSame($this->configuration, $manager->getConfiguration());
        self::assertNull($manager->getSessionId());

        $manager->setSessionId('00000000000000000000000000000000');
        self::assertNull($manager->getSessionId());
    }

    /**
     * @runInSeparateProcess
     *
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Session has already been started. Check "session.auto_start" ini setting
     */
    public function testSessionAlreadyStarted()
    {
        $manager = new Native($this->configuration, $this->handler);

        session_start();

        $manager->sessionStart();
    }

    /**
     * PHPUnit automatically sends headers when not running test with 'runInSeparateProcess'.
     *
     * @expectedException \RuntimeException
     * @expectedExceptionMessageRegExp /^PHP session failed to start because headers have already been sent by/
     */
    public function testHeadersAlreadySent()
    {
        $manager = new Native($this->configuration, $this->handler);

        $manager->sessionStart();
    }

    /**
     * @runInSeparateProcess
     *
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Session identifier cannot be manually altered once session is started
     */
    public function testSetSessionIdNotAllowed()
    {
        $manager = new Native($this->configuration, $this->handler);

        $manager->sessionStart();

        $manager->setSessionId('00000000000000000000000000000000');
    }

    /**
     * @runInSeparateProcess
     *
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Session has already been started
     */
    public function testSessionNoRestart()
    {
        $manager = new Native($this->configuration, $this->handler);

        $manager->sessionStart();
        $manager->sessionStart();
    }

    /**
     * @runInSeparateProcess
     */
    public function testSessionStart()
    {
        $manager = new Native($this->configuration, $this->handler);

        $manager->sessionStart();

        self::assertTrue($manager->isSessionStarted());

        self::assertEquals($this->configuration->getName(), session_name());
        self::assertNotNull($manager->getSessionId());
        self::assertTrue($manager->isSessionStarted());
    }

    /**
     * @runInSeparateProcess
     */
    public function testSessionLoad()
    {
        $handler = new MemoryHandlerStub();

        $manager = new Native($this->configuration, $handler);

        $manager->sessionStart();

        $manager->sessionEnd(['sessionVar' => 'sessionValue']);

        $sessionData = $manager->sessionStart();

        self::assertInternalType('array', $sessionData);
        self::assertTrue(array_key_exists('sessionVar', $sessionData));
    }

    /**
     * @runInSeparateProcess
     *
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Cannot end a not started session
     */
    public function testSessionEndNotAllowed()
    {
        $manager = new Native($this->configuration, $this->handler);

        $manager->sessionEnd();
    }

    /**
     * @runInSeparateProcess
     */
    public function testSessionEnd()
    {
        $manager = new Native($this->configuration, $this->handler);

        $manager->sessionStart();

        $manager->sessionEnd(['sessionKey' => 'sessionValue']);

        self::assertFalse($manager->isSessionStarted());
        self::assertFalse(isset($_SESSION));
    }

    /**
     * @runInSeparateProcess
     *
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Cannot regenerate id a not started session
     */
    public function testSessionRegenerateIdNotAllowed()
    {
        $manager = new Native($this->configuration, $this->handler);

        $manager->sessionRegenerateId();
    }

    /**
     * @runInSeparateProcess
     */
    public function testSessionRegenerateId()
    {
        $manager = new Native($this->configuration, $this->handler);

        $manager->sessionStart();

        self::assertTrue($manager->shouldRegenerateId());

        $originalSessionId = $manager->getSessionId();

        $manager->sessionRegenerateId();

        self::assertNotEquals($originalSessionId, $manager->getSessionId());
        self::assertEquals($this->configuration->getName(), session_name());
        self::assertFalse(isset($_SESSION));
        self::assertFalse($manager->shouldRegenerateId());
    }

    /**
     * @runInSeparateProcess
     *
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Cannot destroy a not started session
     */
    public function testSessionDestroyNotAllowed()
    {
        $manager = new Native($this->configuration, $this->handler);

        $manager->sessionDestroy();
    }

    /**
     * @runInSeparateProcess
     */
    public function testSessionDestroy()
    {
        $manager = new Native($this->configuration, $this->handler);

        $manager->sessionStart();

        $manager->sessionDestroy();

        self::assertFalse(isset($_SESSION));
    }
}
