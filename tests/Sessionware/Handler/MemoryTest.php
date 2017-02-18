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

namespace Jgut\Sessionware\Tests\Handler;

use Jgut\Sessionware\Configuration;
use Jgut\Sessionware\Handler\Memory;
use Jgut\Sessionware\Tests\SessionTestCase;

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
        /* @var Configuration $configuration */

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
