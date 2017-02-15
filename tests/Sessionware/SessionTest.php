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
use Jgut\Middleware\Sessionware\Manager\Native;
use Jgut\Middleware\Sessionware\Session;

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
            ->disableOriginalConstructor()
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
        /* @var \Jgut\Middleware\Sessionware\Manager\Manager $manager */

        $session = new Session($manager);

        self::assertFalse($session->has('sessionKey'));

        $session->set('sessionKeyOne', 'sessionValueOne');
        $session->set('sessionKeyTwo', 'sessionValueTwo');
        self::assertTrue($session->has('sessionKeyOne'));
        self::assertEquals('sessionValueOne', $session->get('sessionKeyOne'));

        $session->remove('sessionKeyOne');
        self::assertFalse($session->has('sessionKeyOne'));
        self::assertEquals('noValue', $session->get('sessionKeyOne', 'noValue'));

        $session->clear();
        self::assertFalse($session->has('sessionKeyTwo'));
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
        /* @var \Jgut\Middleware\Sessionware\Manager\Manager $manager */

        $session = new Session($manager);

        self::assertFalse($session->isActive());

        $session->start();
        $session->start();

        self::assertTrue($session->has(Configuration::TIMEOUT_KEY_DEFAULT));
        self::assertEquals($session->getTimeout(), $session->get(Configuration::TIMEOUT_KEY_DEFAULT));
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
        /* @var \Jgut\Middleware\Sessionware\Manager\Manager $manager */

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
        /* @var \Jgut\Middleware\Sessionware\Manager\Manager $manager */

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
        /* @var \Jgut\Middleware\Sessionware\Manager\Manager $manager */

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
        /* @var \Jgut\Middleware\Sessionware\Manager\Manager $manager */

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
        /* @var \Jgut\Middleware\Sessionware\Manager\Manager $manager */

        $session = new Session($manager);

        $session->start();

        $session->set('saveKey', 'savedValue');

        $session->destroy();

        self::assertFalse($session->has('saveKey'));
    }
}
