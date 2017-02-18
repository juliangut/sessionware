<?php

/*
 * sessionware (https://github.com/juliangut/sessionware).
 * PSR7 session management middleware.
 *
 * @license BSD-3-Clause
 * @link https://github.com/juliangut/sessionware
 * @author Julián Gutiérrez <juliangut@gmail.com>
 */

declare(strict_types=1);

namespace Jgut\Middleware\Sessionware\Tests\Manager;

use Jgut\Middleware\Sessionware\Configuration;
use Jgut\Middleware\Sessionware\Handler\Memory;
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
        $this->handler = new Memory();
    }

    public function testGettersSetters()
    {
        $manager = new Native($this->configuration, $this->handler);

        self::assertFalse($manager->isSessionStarted());
        self::assertSame($this->configuration, $manager->getConfiguration());
        self::assertEmpty($manager->getSessionId());

        $manager->setSessionId('00000000000000000000000000000000');
        self::assertEmpty($manager->getSessionId());
    }

    /**
     * @param string $setting
     * @param string $value
     *
     * @runInSeparateProcess
     * @dataProvider invalidIniSettingsProvider
     *
     * @expectedException \RuntimeException
     * @expectedExceptionMessageRegExp /^"session\..+" ini setting must be set to .+/
     */
    public function testInvalidIniSettings(string $setting, string $value)
    {
        ini_set($setting, $value);

        $manager = new Native($this->configuration);
        $manager->sessionStart();
    }

    /**
     * Provide invalid ini settings.
     *
     * @return array
     */
    public function invalidIniSettingsProvider() : array
    {
        return [
            ['session.use_trans_sid', '1'],
            ['session.use_cookies', '0'],
            ['session.use_only_cookies', '0'],
            ['session.use_strict_mode', '1'],
            ['session.cache_limiter', 'nocache'],
        ];
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

        self::assertEquals('php_serialize', ini_get('session.serialize_handler'));
        self::assertSame($this->configuration->getLifetime(), (int) ini_get('session.gc_maxlifetime'));
        self::assertEquals('user', ini_get('session.save_handler'));
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
        $handler = new Memory();

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
