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

    public function testCreation()
    {
        $manager = $this->getMockBuilder(Native::class)
            ->disableOriginalConstructor()
            ->getMock();
        $manager
            ->expects(self::once())
            ->method('getConfiguration')
            ->will(self::returnValue($this->configuration));
        /* @var \Jgut\Sessionware\Manager\Manager $manager */

        new Session($manager);
    }

    /**
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
        self::assertTrue(isset($session[$this->configuration->getTimeoutKey()]));

        $session->set('sessionKeyOne', 'sessionValueOne');
        self::assertTrue($session->has('sessionKeyOne'));
        self::assertEquals('sessionValueOne', $session->get('sessionKeyOne'));
        self::assertEquals('sessionValueOne', $session['sessionKeyOne']);

        $session->remove('sessionKeyOne');
        self::assertFalse($session->has('sessionKeyOne'));
        self::assertEquals('noValue', $session->get('sessionKeyOne', 'noValue'));
        self::assertNull($session['sessionKeyOne']);

        $session['sessionKeyTwo'] = 'sessionValueTwo';
        unset($session['sessionKeyTwo']);
        self::assertFalse($session->has('sessionKeyTwo'));

        $session->clear();
        self::assertTrue($session->has($this->configuration->getTimeoutKey()));
        self::assertEquals($timeout, $session->get($this->configuration->getTimeoutKey()));
    }

    public function testSessionStart()
    {
        $manager = $this->getMockBuilder(Native::class)
            ->disableOriginalConstructor()
            ->getMock();
        $manager
            ->expects(self::any())
            ->method('isStarted')
            ->will(self::onConsecutiveCalls(false, false, true, true));
        $manager
            ->expects(self::once())
            ->method('start')
            ->will(self::returnValue(['key' => 'value']));
        $manager
            ->expects(self::any())
            ->method('shouldRegenerateId')
            ->will(self::returnValue(true));
        $manager
            ->expects(self::once())
            ->method('regenerateId');
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

    public function testSessionTimeout()
    {
        $manager = $this->getMockBuilder(Native::class)
            ->disableOriginalConstructor()
            ->getMock();
        $manager
            ->expects(self::any())
            ->method('isStarted')
            ->will(self::onConsecutiveCalls(false, false, true, true));
        $manager
            ->expects(self::once())
            ->method('start')
            ->will(self::returnValue([]));
        $manager
            ->expects(self::any())
            ->method('shouldRegenerateId')
            ->will(self::returnValue(false));
        $manager
            ->expects(self::once())
            ->method('regenerateId');
        $manager
            ->expects(self::any())
            ->method('getConfiguration')
            ->will(self::returnValue($this->configuration));
        /* @var \Jgut\Sessionware\Manager\Manager $manager */

        $session = new Session($manager, [$this->configuration->getTimeoutKey() => time() - 3600]);

        $unit = $this;
        $session->addListener(
            'pre.session_timeout',
            function ($event, $session) use ($unit) {
                $unit::assertInstanceOf(Session::class, $session);
            }
        );

        $session->start();
    }

    public function testSessionClose()
    {
        $manager = $this->getMockBuilder(Native::class)
            ->disableOriginalConstructor()
            ->getMock();
        $manager
            ->expects(self::any())
            ->method('isStarted')
            ->will(self::onConsecutiveCalls(false, true, false));
        $manager
            ->expects(self::once())
            ->method('start')
            ->will(self::returnValue([]));
        $manager
            ->expects(self::any())
            ->method('shouldRegenerateId')
            ->will(self::returnValue(false));
        $manager
            ->expects(self::once())
            ->method('close');
        /* @var \Jgut\Sessionware\Manager\Manager $manager */

        $session = new Session($manager);

        $session->start();

        $session->close();
        $session->close();
    }

    /**
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
            ->method('isStarted')
            ->will(self::returnValue(false));
        /* @var \Jgut\Sessionware\Manager\Manager $manager */

        $session = new Session($manager);

        $session->regenerateId();
    }

    public function testSessionRegenerateId()
    {
        $manager = $this->getMockBuilder(Native::class)
            ->disableOriginalConstructor()
            ->getMock();
        $manager
            ->expects(self::any())
            ->method('isStarted')
            ->will(self::onConsecutiveCalls(false, true, true));
        $manager
            ->expects(self::once())
            ->method('start')
            ->will(self::returnValue([]));
        $manager
            ->expects(self::once())
            ->method('regenerateId');
        $manager
            ->expects(self::once())
            ->method('getId')
            ->will(self::returnValue($this->sessionId));
        /* @var \Jgut\Sessionware\Manager\Manager $manager */

        $session = new Session($manager);

        $session->start();

        $session->set('saveKey', 'savedValue');

        $session->regenerateId();

        self::assertEquals($this->sessionId, $session->getId());
        self::assertTrue($session->has('saveKey'));
        self::assertEquals('savedValue', $session->get('saveKey'));
    }

    public function testSessionReset()
    {
        $manager = $this->getMockBuilder(Native::class)
            ->disableOriginalConstructor()
            ->getMock();
        $manager
            ->expects(self::any())
            ->method('isStarted')
            ->will(self::onConsecutiveCalls(false, true, false));
        $manager
            ->expects(self::once())
            ->method('start')
            ->will(self::returnValue([]));
        /* @var \Jgut\Sessionware\Manager\Manager $manager */

        $session = new Session($manager, ['initial' => 'data']);

        $session->start();

        $session->set('other', 'data');

        self::assertEquals('data', $session->get('initial'));
        self::assertEquals('data', $session->get('other'));

        $session->reset();

        self::assertEquals('data', $session->get('initial'));
        self::assertNull($session->get('other'));

        $session->reset();
    }

    public function testSessionAbort()
    {
        $manager = $this->getMockBuilder(Native::class)
            ->disableOriginalConstructor()
            ->getMock();
        $manager
            ->expects(self::any())
            ->method('isStarted')
            ->will(self::onConsecutiveCalls(false, true, false));
        $manager
            ->expects(self::once())
            ->method('start')
            ->will(self::returnValue([]));
        $manager
            ->expects(self::once())
            ->method('close')
            ->with(['initial' => 'data']);
        /* @var \Jgut\Sessionware\Manager\Manager $manager */

        $session = new Session($manager, ['initial' => 'data']);

        $session->start();

        $session->set('other', 'data');

        $session->abort();
        $session->abort();
    }

    /**
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
            ->method('isStarted')
            ->will(self::returnValue(false));
        /* @var \Jgut\Sessionware\Manager\Manager $manager */

        $session = new Session($manager);

        $session->destroy();
    }

    public function testSessionDestroy()
    {
        $manager = $this->getMockBuilder(Native::class)
            ->disableOriginalConstructor()
            ->getMock();
        $manager
            ->expects(self::any())
            ->method('isStarted')
            ->will(self::onConsecutiveCalls(false, true, true));
        $manager
            ->expects(self::any())
            ->method('isDestroyed')
            ->will(self::returnValue(true));
        $manager
            ->expects(self::once())
            ->method('start')
            ->will(self::returnValue([]));
        $manager
            ->expects(self::once())
            ->method('destroy');
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

    public function testSessionId()
    {
        $manager = $this->getMockBuilder(Native::class)
            ->disableOriginalConstructor()
            ->getMock();
        $manager
            ->expects(self::once())
            ->method('setId');
        /* @var \Jgut\Sessionware\Manager\Manager $manager */

        $session = new Session($manager);

        $session->setId('sdfh');
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Cannot set session id on started or destroyed sessions
     */
    public function testSessionIdOnStartedSession()
    {
        $manager = $this->getMockBuilder(Native::class)
            ->disableOriginalConstructor()
            ->getMock();
        $manager
            ->expects(self::any())
            ->method('isStarted')
            ->will(self::onConsecutiveCalls(false, true, true));
        $manager
            ->expects(self::once())
            ->method('start')
            ->will(self::returnValue([]));
        /* @var \Jgut\Sessionware\Manager\Manager $manager */

        $session = new Session($manager);

        $session->start();

        $session->setId('sdfh');
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Cannot set session id on started or destroyed sessions
     */
    public function testSessionIdOnDestroyedSession()
    {
        $manager = $this->getMockBuilder(Native::class)
            ->disableOriginalConstructor()
            ->getMock();
        $manager
            ->expects(self::any())
            ->method('isStarted')
            ->will(self::onConsecutiveCalls(false, true, true));
        $manager
            ->expects(self::once())
            ->method('start')
            ->will(self::returnValue([]));
        $manager
            ->expects(self::once())
            ->method('destroy');
        /* @var \Jgut\Sessionware\Manager\Manager $manager */

        $session = new Session($manager);

        $session->start();
        $session->destroy();

        $session->setId('sdfh');
    }

    public function testNotStartedSessionCookieString()
    {
        $configuration = new Configuration(
            [
                'name' => 'Sessionware',
            ]
        );

        $manager = $this->getMockBuilder(Native::class)
            ->disableOriginalConstructor()
            ->getMock();
        $manager
            ->expects(self::any())
            ->method('getConfiguration')
            ->will(self::returnValue($configuration));
        /* @var \Jgut\Sessionware\Manager\Manager $manager */

        $session = new Session($manager);

        self::assertEquals('', $session->getSessionCookieString());
    }

    public function testDestroyedSessionCookieString()
    {
        $sessionId = 'ch3OZUQU3J93jqFRlbC7t5zzUrXq1m8AmBj87wdaUNZMzKHb9T5sYd8iZItWFR720NfoYmAztV3Izbpt';

        $configuration = new Configuration(
            [
                'name' => 'Sessionware',
                'lifetime' => Configuration::LIFETIME_FLASH,
                'cookiePath' => '/home',
                'cookieDomain' => 'localhost',
                'cookieSecure' => true,
                'cookieHttpOnly' => true,
                'cookieSameSite' => Configuration::SAME_SITE_STRICT,
            ]
        );

        $manager = $this->getMockBuilder(Native::class)
            ->disableOriginalConstructor()
            ->getMock();
        $manager
            ->expects(self::any())
            ->method('getId')
            ->will(self::returnValue($sessionId));
        $manager
            ->expects(self::any())
            ->method('isDestroyed')
            ->will(self::returnValue(true));
        $manager
            ->expects(self::any())
            ->method('getConfiguration')
            ->will(self::returnValue($configuration));
        /* @var \Jgut\Sessionware\Manager\Manager $manager */

        $session = new Session($manager);

        $cookieHeader = $session->getSessionCookieString();

        self::assertSame(strpos($cookieHeader, $configuration->getName() . '=' . $sessionId), 0);
        self::assertNotSame(strpos($cookieHeader, 'max-age=1'), false);
        self::assertNotSame(strpos($cookieHeader, 'path=' . $configuration->getCookiePath()), false);
        self::assertSame(strpos($cookieHeader, 'domain='), false);
        self::assertNotSame(strpos($cookieHeader, 'secure'), false);
        self::assertNotSame(strpos($cookieHeader, 'httponly'), false);
        self::assertNotSame(strpos($cookieHeader, 'SameSite=' . $configuration->getCookieSameSite()), false);
    }

    public function testInitiatedSessionCookieString()
    {
        $sessionId = 'ch3OZUQU3J93jqFRlbC7t5zzUrXq1m8AmBj87wdaUNZMzKHb9T5sYd8iZItWFR720NfoYmAztV3Izbpt';

        $configuration = new Configuration(
            [
                'name' => 'Sessionware',
                'lifetime' => Configuration::LIFETIME_FLASH,
                'cookiePath' => '/home',
                'cookieDomain' => 'localhost',
                'cookieSecure' => true,
                'cookieHttpOnly' => true,
                'cookieSameSite' => Configuration::SAME_SITE_STRICT,
            ]
        );

        $manager = $this->getMockBuilder(Native::class)
            ->disableOriginalConstructor()
            ->getMock();
        $manager
            ->expects(self::any())
            ->method('getId')
            ->will(self::returnValue($sessionId));
        $manager
            ->expects(self::any())
            ->method('getConfiguration')
            ->will(self::returnValue($configuration));
        /* @var \Jgut\Sessionware\Manager\Manager $manager */

        $session = new Session($manager);

        $cookieHeader = $session->getSessionCookieString();

        self::assertSame(strpos($cookieHeader, $configuration->getName() . '=' . $sessionId), 0);
        self::assertNotSame(strpos($cookieHeader, 'max-age=' . $configuration->getLifetime()), false);
        self::assertNotSame(strpos($cookieHeader, 'path=' . $configuration->getCookiePath()), false);
        self::assertSame(strpos($cookieHeader, 'domain='), false);
        self::assertNotSame(strpos($cookieHeader, 'secure'), false);
        self::assertNotSame(strpos($cookieHeader, 'httponly'), false);
        self::assertNotSame(strpos($cookieHeader, 'SameSite=' . $configuration->getCookieSameSite()), false);
    }
}
