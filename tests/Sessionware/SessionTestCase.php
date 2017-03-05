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
use PHPUnit\Framework\TestCase;

/**
 * Session test case class.
 */
abstract class SessionTestCase extends TestCase
{
    /**
     * @var string
     */
    protected $sessionId;

    /**
     * @var string
     */
    protected $sessionName;

    /**
     * @var Configuration
     */
    protected $configuration;

    /**
     * {@inheritdoc}
     */
    public function setUp()
    {
        ini_set('session.use_trans_sid', '0');
        ini_set('session.use_cookies', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_strict_mode', '0');
        ini_set('session.cache_limiter', '');

        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '2');

        $this->sessionId = str_repeat('0', 32);
        $this->sessionName = 'Sessionware';

        $configuration = $this->getMockBuilder(Configuration::class)
            ->disableOriginalConstructor()
            ->getMock();
        $configuration
            ->expects(self::any())
            ->method('getName')
            ->will(self::returnValue($this->sessionName));
        $configuration
            ->expects(self::any())
            ->method('getLifetime')
            ->will(self::returnValue(Configuration::LIFETIME_EXTENDED));
        $configuration
            ->expects(self::any())
            ->method('getTimeoutKey')
            ->will(self::returnValue(Configuration::TIMEOUT_KEY_DEFAULT));
        /* @var Configuration $configuration */

        $this->configuration = $configuration;

        // Default PHP session length
        session_id($this->sessionId);
    }
}
