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
use Jgut\Middleware\Sessionware\Session;
use PHPUnit\Framework\TestCase;

/**
 * PHP session helper test class.
 */
class SessionTest extends TestCase
{
    /**
     * @var Session
     */
    protected $session;

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
        ini_set('session.gc_divisor', 2);

        $savePath = sys_get_temp_dir() . '/Sessionware';
        if (!file_exists($savePath) || !is_dir($savePath)) {
            mkdir($savePath, 0775);
        }
        ini_set('session.save_path', $savePath);
        session_id('00000000000000000000000000000000');

        session_start();

        $configuration = $this->getMockBuilder(Configuration::class)
            ->disableOriginalConstructor()
            ->setMethods(['getLifetime', 'getTimeoutKey'])
            ->getMock();
        $configuration
            ->expects(self::any())
            ->method('getLifetime')
            ->will(self::returnValue(Configuration::LIFETIME_SHORT));
        $configuration
            ->expects(self::any())
            ->method('getTimeoutKey')
            ->will(self::returnValue('__timeout__'));
        /* @var Configuration $configuration */

        $this->session = new Session($configuration);
    }

    /**
     * @runInSeparateProcess
     */
    public function testSessionAccessorMutator()
    {
        self::assertFalse($this->session->has('sessionKey'));

        $this->session->set('sessionKeyOne', 'sessionValueOne');
        $this->session->set('sessionKeyTwo', 'sessionValueTwo');
        self::assertTrue($this->session->has('sessionKeyOne'));
        self::assertEquals('sessionValueOne', $this->session->get('sessionKeyOne'));

        $this->session->remove('sessionKeyOne');
        self::assertFalse($this->session->has('sessionKeyOne'));
        self::assertEquals('noValue', $this->session->get('sessionKeyOne', 'noValue'));

        $this->session->clear();
        self::assertFalse($this->session->has('sessionKeyTwo'));
    }

    /**
     * @runInSeparateProcess
     *
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Cannot regenerate id on a not started session
     */
    public function testSessionRegenerateOnSessionNotStarted()
    {
        $_SESSION = [];
        session_unset();
        session_destroy();

        $this->session->regenerateSessionId();
    }

    /**
     * @runInSeparateProcess
     */
    public function testSessionRegenerate()
    {
        $sessionId = session_id();

        $this->session->set('saveKey', 'savedValue');
        $this->session->regenerateSessionId();

        self::assertNotEquals($sessionId, session_id());
        self::assertTrue($this->session->has('saveKey'));
        self::assertEquals('savedValue', $this->session->get('saveKey'));
    }

    /**
     * @runInSeparateProcess
     */
    public function testSessionTimeout()
    {
        $sessionId = session_id();

        $passedTimeout = time() - 1000;
        $this->session->set('__timeout__', $passedTimeout);

        $assert = $this;
        $sessionHolder = new \stdClass();
        $baseSession = $this->session;
        $this->session->addListener(
            'pre.session_timeout',
            function ($event, $sessionId, $session) use ($assert, $sessionHolder, $baseSession) {
                $assert::assertEquals($baseSession, $session);
                $sessionHolder->id = $sessionId;
            }
        );

        $this->session->addListener(
            'post.session_timeout',
            function ($event, $sessionId, $session) use ($assert, $sessionHolder, $baseSession) {
                $assert::assertNotNull($sessionHolder->id);
                $assert::assertNotEquals($sessionHolder->id, $sessionId);
                $assert::assertEquals($baseSession, $session);
            }
        );

        $this->session->manageTimeout();

        self::assertNotEquals($sessionId, session_id());
        self::assertTrue($this->session->has('__timeout__'));
        self::assertTrue($passedTimeout < $this->session->get('__timeout__'));
    }
}
