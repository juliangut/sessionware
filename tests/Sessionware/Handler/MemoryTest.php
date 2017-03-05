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
use Jgut\Sessionware\Handler\Memory;

/**
 * Void session handler test class.
 */
class MemoryTest extends HandlerTestCase
{
    /**
     * @var Memory
     */
    protected $handler;

    /**
     * {@inheritdoc}
     */
    public function setUp()
    {
        parent::setUp();

        $this->handler = new Memory();
        $this->handler->setConfiguration($this->configuration);
    }

    public function testOpenClose()
    {
        self::assertTrue($this->handler->open('not', 'used'));
        self::assertTrue($this->handler->close());
    }

    public function testAccessors()
    {
        self::assertEquals(serialize([]), $this->handler->read($this->sessionId));

        self::assertTrue($this->handler->write($this->sessionId, serialize([])));
        self::assertEquals(serialize([]), $this->handler->read($this->sessionId));
    }

    public function testDestroy()
    {
        $this->handler->write($this->sessionId, $this->sessionData);

        self::assertTrue($this->handler->destroy($this->sessionId));
        self::assertEquals(serialize([]), $this->handler->read($this->sessionId));

        self::assertTrue($this->handler->gc(Configuration::SESSION_NAME_DEFAULT));
    }

    public function testGarbageCollector()
    {
        self::assertTrue($this->handler->gc(Configuration::SESSION_NAME_DEFAULT));
    }
}
