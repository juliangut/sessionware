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

namespace Jgut\Sessionware\Tests\Handler;

use Jgut\Sessionware\Tests\SessionTestCase;

/**
 * Session handler test case class.
 */
abstract class HandlerTestCase extends SessionTestCase
{
    /**
     * @var string
     */
    protected $sessionData = 'a:1:{s:10:"sessionKey";s:11:"sessionData";}';
}
