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

namespace Jgut\Sessionware\Tests;

use Jgut\Sessionware\Configuration;
use Jgut\Sessionware\Manager\Native;
use Jgut\Sessionware\Session;

/**
 * PHP session helper test class.
 */
class SessionTest extends SessionTestCase
{
    /**
     * @var Configuration
     */
    protected $configuration;

    /**
     * @var Session
     */
    protected $session;

    /**
     * {@inheritdoc}
     */
    public function setUp()
    {
        parent::setUp();

        $configuration = $this->getMockBuilder(Configuration::class)
            ->setMethods(['getName', 'getLifetime', 'getTimeoutKey'])
            ->getMock();
        $configuration
            ->expects(self::any())
            ->method('getName')
            ->will(self::returnValue('Sessionware'));
        $configuration
            ->expects(self::any())
            ->method('getLifetime')
            ->will(self::returnValue(Configuration::LIFETIME_DEFAULT));
        $configuration
            ->expects(self::any())
            ->method('getTimeoutKey')
            ->will(self::returnValue(Configuration::TIMEOUT_KEY_DEFAULT));
        /* @var Configuration $configuration */

        $this->configuration = $configuration;
    }

    /**
     * @runInSeparateProcess
     *
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Session values must be scalars, object given
     */
    public function testInvalidSessionValue()
    {
        $manager = $this->getMockBuilder(Native::class)
            ->disableOriginalConstructor()
            ->getMock();
        $manager
            ->expects(self::any())
            ->method('getConfiguration')
            ->will(self::returnValue($this->configuration));
        /* @var \Jgut\Sessionware\Manager\Manager $manager */

        $session = new Session($manager);

        $session->set('sessionKey', [new \stdClass()]);
    }

    /**
     * @runInSeparateProcess
     */
    public function testSessionSettersGetters()
    {
        $manager = $this->getMockBuilder(Native::class)
            ->disableOriginalConstructor()
            ->getMock();
        $manager
            ->expects(self::any())
            ->method('getConfiguration')
            ->will(self::returnValue($this->configuration));
        /* @var \Jgut\Sessionware\Manager\Manager $manager */

        $timeout = time() + Configuration::LIFETIME_FLASH;
        $session = new Session($manager, [$this->configuration->getTimeoutKey() => $timeout]);

        self::assertTrue($session->has($this->configuration->getTimeoutKey()));

        $session->set('sessionKeyOne', 'sessionValueOne');
        self::assertTrue($session->has('sessionKeyOne'));
        self::assertEquals('sessionValueOne', $session->get('sessionKeyOne'));

        $session->remove('sessionKeyOne');
        self::assertFalse($session->has('sessionKeyOne'));
        self::assertEquals('noValue', $session->get('sessionKeyOne', 'noValue'));

        $session->set('sessionKeyTwo', 'sessionValueTwo');

        $session->clear();
        self::assertFalse($session->has('sessionKeyTwo'));
        self::assertTrue($session->has($this->configuration->getTimeoutKey()));
        self::assertEquals($timeout, $session->get($this->configuration->getTimeoutKey()));
    }

    /**
     * @runInSeparateProcess
     */
    public function testSessionStart()
    {
        $manager = $this->getMockBuilder(Native::class)
            ->disableOriginalConstructor()
            ->getMock();
        $manager
            ->expects(self::any())
            ->method('isSessionStarted')
            ->will(self::onConsecutiveCalls(false, false, true, true));
        $manager
            ->expects(self::once())
            ->method('sessionStart')
            ->will(self::returnValue([]));
        $manager
            ->expects(self::any())
            ->method('shouldRegenerateId')
            ->will(self::returnValue(true));
        $manager
            ->expects(self::once())
            ->method('sessionRegenerateId');
        $manager
            ->expects(self::any())
            ->method('getConfiguration')
            ->will(self::returnValue($this->configuration));
        /* @var \Jgut\Sessionware\Manager\Manager $manager */

        $timeout = time() + Configuration::LIFETIME_FLASH;
        $session = new Session($manager, [$this->configuration->getTimeoutKey() => $timeout]);

        self::assertFalse($session->isActive());
        self::assertFalse($session->isDestroyed());

        $session->start();
        $session->start();

        self::assertTrue($session->has($this->configuration->getTimeoutKey()));
        self::assertNotEquals($timeout, $session->get($this->configuration->getTimeoutKey()));
    }

    /**
     * @runInSeparateProcess
     */
    public function testSessionTimeout()
    {
        $manager = $this->getMockBuilder(Native::class)
            ->disableOriginalConstructor()
            ->getMock();
        $manager
            ->expects(self::any())
            ->method('isSessionStarted')
            ->will(self::onConsecutiveCalls(false, false, true, true));
        $manager
            ->expects(self::once())
            ->method('sessionStart')
            ->will(self::returnValue([]));
        $manager
            ->expects(self::any())
            ->method('shouldRegenerateId')
            ->will(self::returnValue(false));
        $manager
            ->expects(self::once())
            ->method('sessionRegenerateId');
        $manager
            ->expects(self::any())
            ->method('getConfiguration')
            ->will(self::returnValue($this->configuration));
        /* @var \Jgut\Sessionware\Manager\Manager $manager */

        $session = new Session($manager, [$this->configuration->getTimeoutKey() => time() - 3600]);

        $session->start();
    }

    /**
     * @runInSeparateProcess
     */
    public function testSessionClose()
    {
        $manager = $this->getMockBuilder(Native::class)
            ->disableOriginalConstructor()
            ->getMock();
        $manager
            ->expects(self::any())
            ->method('isSessionStarted')
            ->will(self::onConsecutiveCalls(false, true, false));
        $manager
            ->expects(self::once())
            ->method('sessionStart')
            ->will(self::returnValue([]));
        $manager
            ->expects(self::any())
            ->method('shouldRegenerateId')
            ->will(self::returnValue(false));
        $manager
            ->expects(self::once())
            ->method('sessionEnd');
        $manager
            ->expects(self::any())
            ->method('getConfiguration')
            ->will(self::returnValue($this->configuration));
        /* @var \Jgut\Sessionware\Manager\Manager $manager */

        $session = new Session($manager);

        $session->start();

        $session->close();
        $session->close();
    }

    /**
     * @runInSeparateProcess
     *
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Cannot regenerate a not started session
     */
    public function testSessionRegenerateIdOnSessionNotStarted()
    {
        $manager = $this->getMockBuilder(Native::class)
            ->disableOriginalConstructor()
            ->getMock();
        $manager
            ->expects(self::any())
            ->method('isSessionStarted')
            ->will(self::returnValue(false));
        /* @var \Jgut\Sessionware\Manager\Manager $manager */

        $session = new Session($manager);

        $session->regenerateId();
    }

    /**
     * @runInSeparateProcess
     */
    public function testSessionRegenerateId()
    {
        $manager = $this->getMockBuilder(Native::class)
            ->disableOriginalConstructor()
            ->getMock();
        $manager
            ->expects(self::any())
            ->method('isSessionStarted')
            ->will(self::onConsecutiveCalls(false, true, true));
        $manager
            ->expects(self::once())
            ->method('sessionStart')
            ->will(self::returnValue([]));
        $manager
            ->expects(self::once())
            ->method('sessionRegenerateId');
        $manager
            ->expects(self::once())
            ->method('getSessionId')
            ->will(self::returnValue('00000000000000000000000000000000'));
        $manager
            ->expects(self::any())
            ->method('getConfiguration')
            ->will(self::returnValue($this->configuration));
        /* @var \Jgut\Sessionware\Manager\Manager $manager */

        $session = new Session($manager);

        $session->start();

        $session->set('saveKey', 'savedValue');

        $session->regenerateId();

        self::assertEquals('00000000000000000000000000000000', $session->getId());
        self::assertTrue($session->has('saveKey'));
        self::assertEquals('savedValue', $session->get('saveKey'));
    }

    /**
     * @runInSeparateProcess
     *
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Cannot destroy a not started session
     */
    public function testSessionDestroyOnSessionNotStarted()
    {
        $manager = $this->getMockBuilder(Native::class)
            ->disableOriginalConstructor()
            ->getMock();
        $manager
            ->expects(self::any())
            ->method('isSessionStarted')
            ->will(self::returnValue(false));
        /* @var \Jgut\Sessionware\Manager\Manager $manager */

        $session = new Session($manager);

        $session->destroy();
    }

    /**
     * @runInSeparateProcess
     */
    public function testSessionDestroy()
    {
        $manager = $this->getMockBuilder(Native::class)
            ->disableOriginalConstructor()
            ->getMock();
        $manager
            ->expects(self::any())
            ->method('isSessionStarted')
            ->will(self::onConsecutiveCalls(false, true, true));
        $manager
            ->expects(self::any())
            ->method('isSessionDestroyed')
            ->will(self::returnValue(true));
        $manager
            ->expects(self::once())
            ->method('sessionStart')
            ->will(self::returnValue([]));
        $manager
            ->expects(self::once())
            ->method('sessionDestroy');
        $manager
            ->expects(self::any())
            ->method('getConfiguration')
            ->will(self::returnValue($this->configuration));
        /* @var \Jgut\Sessionware\Manager\Manager $manager */

        $session = new Session($manager);

        $session->start();

        $session->set('saveKey', 'savedValue');

        $session->destroy();

        self::assertFalse($session->has('saveKey'));
        self::assertTrue($session->isDestroyed());
    }

    /**
     * @runInSeparateProcess
     */
    public function testSessionCookieString()
    {
        $sessionId = 'ch3OZUQU3J93jqFRlbC7t5zzUrXq1m8AmBj87wdaUNZMzKHb9T5sYd8iZItWFR720NfoYmAztV3Izbpt';

        $configuration = new Configuration(
            [
                'name' => 'Sessionware',
                'lifetime' => Configuration::LIFETIME_FLASH,
                'cookiePath' => '/home',
                'cookieDomain' => 'example.com',
                'cookieSecure' => true,
                'cookieHttpOnly' => true,
            ]
        );

        $manager = $this->getMockBuilder(Native::class)
            ->disableOriginalConstructor()
            ->getMock();
        $manager
            ->expects(self::any())
            ->method('getSessionId')
            ->will(self::returnValue($sessionId));
        $manager
            ->expects(self::any())
            ->method('getConfiguration')
            ->will(self::returnValue($configuration));
        /* @var \Jgut\Sessionware\Manager\Manager $manager */

        $session = new Session($manager);

        $cookieHeader = $session->getCookieString();

        self::assertSame(strpos($cookieHeader, $configuration->getName() . '=' . $sessionId), 0);
        self::assertNotSame(strpos($cookieHeader, 'max-age=' . $configuration->getLifetime()), false);
        self::assertNotSame(strpos($cookieHeader, 'path=' . $configuration->getCookiePath()), false);
        self::assertNotSame(strpos($cookieHeader, 'domain=' . $configuration->getCookieDomain()), false);
        self::assertNotSame(strpos($cookieHeader, 'secure'), false);
        self::assertNotSame(strpos($cookieHeader, 'httponly'), false);
    }
}
