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

use Jgut\Sessionware\Configuration;
use Jgut\Sessionware\Handler\Redis;

/**
 * Redis session handler test class.
 */
class RedisTest extends HandlerTestCase
{
    /**
     * {@inheritdoc}
     */
    public function setUp()
    {
        if (!class_exists('Redis', false)) {
            self::markTestSkipped('"ext-redis" is needed to run this tests');
        }

        parent::setUp();
    }

    /**
     * @runInSeparateProcess
     */
    public function testUse()
    {
        $driver = $this->getMockBuilder(\Redis::class)
            ->disableOriginalConstructor()
            ->getMock();
        $driver
            ->expects(self::any())
            ->method('expire');
        $driver
            ->expects(self::any())
            ->method('get')
            ->will(self::returnValue($this->sessionData));
        $driver
            ->expects(self::any())
            ->method('set')
            ->will(self::returnValue(true));
        $driver
            ->expects(self::any())
            ->method('del')
            ->will(self::returnValue(true));
        /* @var \Redis $driver */

        $handler = new Redis($driver);
        $handler->setConfiguration($this->configuration);

        self::assertTrue($handler->open(sys_get_temp_dir(), Configuration::SESSION_NAME_DEFAULT));
        self::assertTrue($handler->close());
        self::assertEquals($this->sessionData, $handler->read('00000000000000000000000000000000'));
        self::assertTrue($handler->write('00000000000000000000000000000000', $this->sessionData));
        self::assertTrue($handler->destroy('00000000000000000000000000000000'));
        self::assertTrue($handler->gc(Configuration::LIFETIME_EXTENDED));
    }
}
