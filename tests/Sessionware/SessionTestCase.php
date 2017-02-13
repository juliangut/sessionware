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

use PHPUnit\Framework\TestCase;

/**
 * Session test case class.
 */
abstract class SessionTestCase extends TestCase
{
    /**
     * {@inheritdoc}
     */
    public function setUp()
    {
        ini_set('session.use_trans_sid', false);
        ini_set('session.use_cookies', true);
        ini_set('session.use_only_cookies', true);
        ini_set('session.use_strict_mode', false);
        ini_set('session.cache_limiter', '');

        ini_set('session.gc_probability', 1);
        ini_set('session.gc_divisor', 2);

        // Default PHP session length
        session_id(str_repeat('0', 32));
    }
}
