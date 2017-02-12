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
use Jgut\Middleware\Sessionware\Handler\Void;
use Jgut\Middleware\Sessionware\Manager\Native;
use Jgut\Middleware\Sessionware\Tests\SessionTestCase;

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
        $this->handler = new Void();
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
     */
    public function testSessionStart()
    {
        $manager = new Native($this->configuration, $this->handler);

        $manager->sessionStart();

        self::assertTrue($manager->isSessionStarted());

        $manager->sessionStart();

        self::assertNotNull($manager->getSessionId());
        self::assertTrue($manager->isSessionStarted());
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

        self::assertTrue(isset($_SESSION));

        $manager->sessionEnd(['sessionKey' => 'sessionValue']);

        self::assertFalse($manager->isSessionStarted());
        self::assertFalse(isset($_SESSION));
    }

    /**
     * @runInSeparateProcess
     *
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Cannot reset a not started session
     */
    public function testSessionResetNotAllowed()
    {
        $manager = new Native($this->configuration, $this->handler);

        $manager->sessionReset();
    }

    /**
     * @runInSeparateProcess
     */
    public function testSessionReset()
    {
        $manager = new Native($this->configuration, $this->handler);

        $manager->sessionStart();

        self::assertTrue(isset($_SESSION));
        self::assertTrue($manager->shouldRegenerate());

        $originalSessionId = $manager->getSessionId();

        $manager->sessionReset();

        self::assertNotEquals($originalSessionId, $manager->getSessionId());
        self::assertTrue(isset($_SESSION));
        self::assertFalse($manager->shouldRegenerate());
    }

    /**
     * @runInSeparateProcess
     *
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Cannot load data from a not started session
     */
    public function testSessionDataLoadNotAllowed()
    {
        $manager = new Native($this->configuration, $this->handler);

        $manager->loadSessionData();
    }

    /**
     * @runInSeparateProcess
     */
    public function testDataLoadFromClosedSession()
    {
        $manager = new Native($this->configuration, $this->handler);

        $manager->sessionStart();

        $_SESSION[$this->configuration->getName() . '.savedKey'] = 'savedValue';

        unset($_SESSION);

        $loadedData = $manager->loadSessionData();

        self::assertEquals([], $loadedData);
    }

    /**
     * @runInSeparateProcess
     */
    public function testSessionDataLoad()
    {
        $manager = new Native($this->configuration, $this->handler);

        $manager->sessionStart();

        $_SESSION[$this->configuration->getName() . '.savedKey'] = 'savedValue';

        $loadedData = $manager->loadSessionData();

        self::assertTrue(isset($loadedData['savedKey']));
        self::assertEquals('savedValue', $loadedData['savedKey']);
    }
}