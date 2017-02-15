<?php

/*
 * sessionware (https://github.com/juliangut/sessionware).
 * PSR7 session management middleware.
 *
 * @license BSD-3-Clause
 * @link https://github.com/juliangut/sessionware
 * @author Julián Gutiérrez <juliangut@gmail.com>
 */

namespace Jgut\Middleware\Sessionware\Tests\Handler;

use Jgut\Middleware\Sessionware\Configuration;
use Jgut\Middleware\Sessionware\Handler\Memory;
use Jgut\Middleware\Sessionware\Tests\SessionTestCase;

/**
 * Void session handler test class.
 */
class MemoryTest extends SessionTestCase
{
    /**
     * @runInSeparateProcess
     */
    public function testUse()
    {
        $configuration = $this->getMockBuilder(Configuration::class)
            ->disableOriginalConstructor()
            ->getMock();

        $handler = new Memory();
        $handler->setConfiguration($configuration);

        self::assertTrue($handler->open(sys_get_temp_dir(), Configuration::SESSION_NAME_DEFAULT));
        self::assertTrue($handler->close());
        self::assertEquals('a:0:{}', $handler->read('00000000000000000000000000000000'));
        $sessionData = serialize(['sessionVar' => 'sessionValue']);
        self::assertTrue($handler->write('00000000000000000000000000000000', $sessionData));
        self::assertEquals($sessionData, $handler->read('00000000000000000000000000000000'));
        self::assertTrue($handler->destroy('00000000000000000000000000000000'));
        self::assertTrue($handler->gc(Configuration::SESSION_NAME_DEFAULT));
    }
}
