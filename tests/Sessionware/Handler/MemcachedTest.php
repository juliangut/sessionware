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

    /**
     * @runInSeparateProcess
     */
    public function testUse()
    {
        $driver = $this->getMockBuilder(\Memcached::class)
            ->disableOriginalConstructor()
            ->getMock();
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
            ->method('delete')
            ->will(self::returnValue(true));
        /* @var \Memcached $driver */

        $handler = new Memcached($driver);
        $handler->setConfiguration($this->configuration);

        self::assertTrue($handler->open(sys_get_temp_dir(), Configuration::SESSION_NAME_DEFAULT));
        self::assertTrue($handler->close());
        self::assertEquals($this->sessionData, $handler->read('00000000000000000000000000000000'));
        self::assertTrue($handler->write('00000000000000000000000000000000', $this->sessionData));
        self::assertTrue($handler->destroy('00000000000000000000000000000000'));
        self::assertTrue($handler->gc(Configuration::LIFETIME_EXTENDED));
    }
}
