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
use Jgut\Sessionware\Handler\Predis;
use Predis\Client;

/**
 * Predis session handler test class.
 */
class PredisTest extends HandlerTestCase
{
    public function testOpenClose()
    {
        $driver = $this->getMockBuilder(Client::class)
            ->disableOriginalConstructor()
            ->getMock();
        /* @var Client $driver */

        $handler = new Predis($driver);
        $handler->setConfiguration($this->configuration);

        self::assertTrue($handler->open('not', 'used'));
        self::assertTrue($handler->close());
    }

    public function testAccessors()
    {
        $driver = $this->getMockBuilder(Client::class)
            ->disableOriginalConstructor()
            ->getMock();
        $driver
            ->expects(self::any())
            ->method('__call')
            ->withConsecutive(['get'], ['expire'], ['set'], ['expire'])
            ->will(self::onConsecutiveCalls($this->sessionData, true, true, true));
        /* @var Client $driver */

        $handler = new Predis($driver);
        $handler->setConfiguration($this->configuration);

        self::assertEquals($this->sessionData, $handler->read($this->sessionId));

        self::assertTrue($handler->write($this->sessionId, serialize([])));
    }

    public function testDestroy()
    {
        $driver = $this->getMockBuilder(Client::class)
            ->disableOriginalConstructor()
            ->getMock();
        $driver
            ->expects(self::any())
            ->method('__call')
            ->will(self::returnValue(true));
        /* @var Client $driver */

        $handler = new Predis($driver);
        $handler->setConfiguration($this->configuration);

        self::assertTrue($handler->destroy($this->sessionId));
    }

    public function testGarbageCollector()
    {
        $driver = $this->getMockBuilder(Client::class)
            ->disableOriginalConstructor()
            ->getMock();
        /* @var Client $driver */

        $handler = new Predis($driver);
        $handler->setConfiguration($this->configuration);

        self::assertTrue($handler->gc(Configuration::LIFETIME_EXTENDED));
    }
}
