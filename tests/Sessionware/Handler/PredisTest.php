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

use Defuse\Crypto\Crypto;
use Jgut\Sessionware\Configuration;
use Jgut\Sessionware\Handler\Predis;
use Predis\Client;

/**
 * Predis session handler test class.
 */
class PredisTest extends HandlerTestCase
{
    /**
     * @runInSeparateProcess
     */
    public function testUse()
    {
        $configuration = $this->getMockBuilder(Configuration::class)
            ->disableOriginalConstructor()
            ->getMock();
        $configuration
            ->expects(self::any())
            ->method('getName')
            ->will(self::returnValue('Sessionware'));
        $configuration
            ->expects(self::any())
            ->method('getLifetime')
            ->will(self::returnValue(Configuration::LIFETIME_EXTENDED));
        $configuration
            ->expects(self::any())
            ->method('getEncryptionKey')
            ->will(self::returnValue('super_secret_key'));
        /* @var Configuration $configuration */

        $sessionData = Crypto::encryptWithPassword(
            $this->sessionData,
            str_pad($configuration->getEncryptionKey(), 32, '=')
        );

        $driver = $this->getMockBuilder(Client::class)
            ->disableOriginalConstructor()
            ->getMock();
        $driver
            ->expects(self::any())
            ->method('__call')
            ->withConsecutive(['get'], ['expire'], ['set'], ['expire'], ['del'])
            ->will(self::onConsecutiveCalls($sessionData, true, true, true, true));
        /* @var Client $driver */

        $handler = new Predis($driver);
        $handler->setConfiguration($configuration);

        self::assertTrue($handler->open(sys_get_temp_dir(), Configuration::SESSION_NAME_DEFAULT));
        self::assertTrue($handler->close());
        self::assertEquals($this->sessionData, $handler->read('00000000000000000000000000000000'));
        self::assertTrue($handler->write('00000000000000000000000000000000', $this->sessionData));
        self::assertTrue($handler->destroy('00000000000000000000000000000000'));
        self::assertTrue($handler->gc(Configuration::LIFETIME_EXTENDED));
    }
}
