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

namespace Jgut\Sessionware\Tests;

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
        ini_set('session.use_trans_sid', '0');
        ini_set('session.use_cookies', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_strict_mode', '0');
        ini_set('session.cache_limiter', '');

        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '2');

        // Default PHP session length
        session_id(str_repeat('0', 32));
    }
}
