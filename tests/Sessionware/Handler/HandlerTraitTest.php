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

use Defuse\Crypto\Key;
use Jgut\Sessionware\Tests\Stubs\HandlerStub;

/**
 * Session handler utility trait test case class.
 */
class HandlerTraitTest extends HandlerTestCase
{
    /**
     * @var HandlerStub
     */
    protected $handler;

    /**
     * {@inheritdoc}
     */
    public function setUp()
    {
        parent::setUp();

        $this->handler = new HandlerStub();
        $this->handler->setConfiguration($this->configuration);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Configuration must be set prior to use
     */
    public function testNoConfiguration()
    {
        $handler = new HandlerStub();

        $handler->testConfiguration();
    }

    public function testDefaultDecryption()
    {
        self::assertEquals(serialize([]), $this->handler->decryptData(''));
    }

    public function testInvalidDecryption()
    {
        self::assertEquals(serialize([]), $this->handler->decryptData('not_really_encrypted_string'));
    }

    public function testNoEncryptionDecryption()
    {
        $plainData = serialize(['data' => 'sessionData']);
        $encryptedData = $this->handler->encryptData($plainData);

        self::assertEquals($plainData, $this->handler->decryptData($encryptedData));
    }

    public function testEncryptionDecryption()
    {
        $this->configuration
            ->expects(self::any())
            ->method('getEncryptionKey')
            ->will(self::returnValue(Key::createNewRandomKey()));

        $plainData = serialize(['data' => 'sessionData']);
        $encryptedData = $this->handler->encryptData($plainData);

        self::assertEquals($plainData, $this->handler->decryptData($encryptedData));
    }
}
