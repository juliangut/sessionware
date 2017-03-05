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

    public function testOpenClose()
    {
        $driver = $this->getMockBuilder(\Redis::class)
            ->disableOriginalConstructor()
            ->getMock();
        /* @var \Redis $driver */

        $handler = new Redis($driver);
        $handler->setConfiguration($this->configuration);

        self::assertTrue($handler->open('not', 'used'));
        self::assertTrue($handler->close());
    }

    public function testAccessors()
    {
        $driver = $this->getMockBuilder(\Redis::class)
            ->disableOriginalConstructor()
            ->getMock();
        $driver
            ->expects(self::once())
            ->method('get')
            ->will(self::returnValue($this->sessionData));
        $driver
            ->expects(self::once())
            ->method('set')
            ->will(self::returnValue(true));
        $driver
            ->expects(self::exactly(2))
            ->method('expire');
        /* @var \Redis $driver */

        $handler = new Redis($driver);
        $handler->setConfiguration($this->configuration);

        self::assertEquals($this->sessionData, $handler->read($this->sessionId));

        self::assertTrue($handler->write($this->sessionId, serialize([])));
    }

    public function testDestroy()
    {
        $driver = $this->getMockBuilder(\Redis::class)
            ->disableOriginalConstructor()
            ->getMock();
        $driver
            ->expects(self::once())
            ->method('del')
            ->will(self::returnValue(true));
        /* @var \Redis $driver */

        $handler = new Redis($driver);
        $handler->setConfiguration($this->configuration);

        self::assertTrue($handler->destroy($this->sessionId));
    }

    public function testGarbageCollector()
    {
        $driver = $this->getMockBuilder(\Redis::class)
            ->disableOriginalConstructor()
            ->getMock();
        /* @var \Redis $driver */

        $handler = new Redis($driver);
        $handler->setConfiguration($this->configuration);

        self::assertTrue($handler->gc(Configuration::LIFETIME_EXTENDED));
    }
}
