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
use Jgut\Sessionware\Handler\Memcached;

/**
 * Memcached session handler test class.
 */
class MemcachedTest extends HandlerTestCase
{
    /**
     * {@inheritdoc}
     */
    public function setUp()
    {
        if (!class_exists('Memcached', false)) {
            self::markTestSkipped('"ext-memcached" is needed to run this tests');
        }

        parent::setUp();
    }

    public function testOpenClose()
    {
        $driver = $this->getMockBuilder(\Memcached::class)
            ->disableOriginalConstructor()
            ->getMock();
        /* @var \Memcached $driver */

        $handler = new Memcached($driver);
        $handler->setConfiguration($this->configuration);

        self::assertTrue($handler->open('not', 'used'));
        self::assertTrue($handler->close());
    }

    public function testAccessors()
    {
        $driver = $this->getMockBuilder(\Memcached::class)
            ->disableOriginalConstructor()
            ->getMock();
        $driver
            ->expects(self::once())
            ->method('get')
            ->will(self::returnValue($this->sessionData));
        $driver
            ->expects(self::once())
            ->method('touch');
        $driver
            ->expects(self::once())
            ->method('set')
            ->will(self::returnValue(true));
        /* @var \Memcached $driver */

        $handler = new Memcached($driver);
        $handler->setConfiguration($this->configuration);

        self::assertEquals($this->sessionData, $handler->read($this->sessionId));

        self::assertTrue($handler->write($this->sessionId, serialize([])));
    }

    public function testDestroy()
    {
        $driver = $this->getMockBuilder(\Memcached::class)
            ->disableOriginalConstructor()
            ->getMock();
        $driver
            ->expects(self::once())
            ->method('delete')
            ->will(self::returnValue(true));
        /* @var \Memcached $driver */

        $handler = new Memcached($driver);
        $handler->setConfiguration($this->configuration);

        self::assertTrue($handler->destroy($this->sessionId));
        self::assertTrue($handler->gc(Configuration::LIFETIME_EXTENDED));
    }

    public function testGarbageCollector()
    {
        $driver = $this->getMockBuilder(\Memcached::class)
            ->disableOriginalConstructor()
            ->getMock();
        /* @var \Memcached $driver */

        $handler = new Memcached($driver);
        $handler->setConfiguration($this->configuration);

        self::assertTrue($handler->gc(Configuration::LIFETIME_EXTENDED));
    }
}
