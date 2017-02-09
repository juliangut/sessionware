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
        if (session_status() !== PHP_SESSION_ACTIVE) {
            // Set a high probability to launch garbage collector
            ini_set('session.gc_probability', 1);
            ini_set('session.gc_divisor', 4);

            session_start();
        }

        $this->session = new Session;
    }

    /**
     * @runInSeparateProcess
     */
    public function testSessionAccessorMuttator()
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
     */
    public function testSessionGenerate()
    {
        $sessionId = session_id();

        $this->session->set('saveKey', 'safeValue');
        $this->session->regenerate();

        self::assertNotEquals($sessionId, session_id());
        self::assertTrue($this->session->has('saveKey'));
        self::assertEquals('safeValue', $this->session->get('saveKey'));
    }
}
