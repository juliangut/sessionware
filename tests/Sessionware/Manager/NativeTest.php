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

namespace Jgut\Sessionware\Tests\Manager;

use Jgut\Sessionware\Configuration;
use Jgut\Sessionware\Handler\Memory;
use Jgut\Sessionware\Manager\Native;
use Jgut\Sessionware\Tests\SessionTestCase;

/**
 * Native PHP session handler test class.
 */
class NativeTest extends SessionTestCase
{
    /**
     * @var \Jgut\Sessionware\Handler\Handler
     */
    protected $handler;

    /**
     * {@inheritdoc}
     */
    public function setUp()
    {
        parent::setUp();

        $this->handler = new Memory();
    }

    public function testConfigureIniSettings()
    {
        $manager = new Native($this->configuration, $this->handler);

        $manager->configureIniSettings();

        self::assertEquals(false, (bool) ini_get('session.use_trans_sid'));
        self::assertEquals(true, (bool) ini_get('session.use_cookies'));
        self::assertEquals(true, (bool) ini_get('session.use_only_cookies'));
        self::assertEquals(false, (bool) ini_get('session.use_strict_mode'));
        self::assertEquals('', ini_get('session.cache_limiter'));
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
        $manager->start();
    }

    /**
     * Provide invalid ini settings.
     *
     * @return array
     */
    public function invalidIniSettingsProvider(): array
    {
        return [
            ['session.use_trans_sid', '1'],
            ['session.use_cookies', '0'],
            ['session.use_only_cookies', '0'],
            ['session.use_strict_mode', '1'],
            ['session.cache_limiter', 'nocache'],
        ];
    }

    public function testGettersSetters()
    {
        $manager = new Native($this->configuration, $this->handler);

        self::assertFalse($manager->isStarted());
        self::assertFalse($manager->isDestroyed());
        self::assertSame($this->configuration, $manager->getConfiguration());
        self::assertEmpty($manager->getId());

        $manager->setId($this->sessionId);
        self::assertEquals($this->sessionId, $manager->getId());
    }

    /**
     * @runInSeparateProcess
     *
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Cannot start a session that has been previously destroyed
     */
    public function testNoSessionStartWhenDestroyed()
    {
        $manager = new Native($this->configuration, $this->handler);

        $manager->start();
        $manager->destroy();

        $manager->start();
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

        $manager->start();
        $manager->start();
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

        $manager->start();
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

        $manager->start();

        $manager->setId($this->sessionId);
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

        $manager->start();
        $manager->start();
    }

    /**
     * @runInSeparateProcess
     */
    public function testSessionStart()
    {
        $manager = new Native($this->configuration, $this->handler);

        $manager->start();

        self::assertEquals('php_serialize', ini_get('session.serialize_handler'));
        self::assertSame($this->configuration->getLifetime(), (int) ini_get('session.gc_maxlifetime'));
        self::assertEquals('user', ini_get('session.save_handler'));
        self::assertEquals('user', session_module_name());
        self::assertTrue($manager->isStarted());

        self::assertEquals($this->configuration->getName(), session_name());
        self::assertNotNull($manager->getId());
        self::assertTrue($manager->isStarted());
    }

    /**
     * @runInSeparateProcess
     */
    public function testSessionLoad()
    {
        $handler = new Memory();

        $manager = new Native($this->configuration, $handler);

        $manager->start();

        $manager->close(['sessionVar' => 'sessionValue']);

        $sessionData = $manager->start();

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

        $manager->close();
    }

    /**
     * @runInSeparateProcess
     */
    public function testSessionEnd()
    {
        $manager = new Native($this->configuration, $this->handler);

        $manager->start();

        $manager->close(['sessionKey' => 'sessionValue']);

        self::assertFalse($manager->isStarted());
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

        $manager->regenerateId();
    }

    /**
     * @runInSeparateProcess
     */
    public function testSessionRegenerateId()
    {
        $manager = new Native($this->configuration, $this->handler);

        $manager->start();

        self::assertTrue($manager->shouldRegenerateId());

        $originalSessionId = $manager->getId();

        $manager->regenerateId();

        self::assertNotEquals($originalSessionId, $manager->getId());
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

        $manager->destroy();
    }

    /**
     * @runInSeparateProcess
     */
    public function testSessionDestroy()
    {
        $manager = new Native($this->configuration, $this->handler);

        $manager->start();

        $manager->destroy();

        self::assertFalse(isset($_SESSION));
        self::assertTrue($manager->isDestroyed());
    }
}
