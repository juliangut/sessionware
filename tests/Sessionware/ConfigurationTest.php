<?php

/*
 * sessionware (https://github.com/juliangut/sessionware).
 * PSR7 session management middleware.
 *
 * @license BSD-3-Clause
 * @link https://github.com/juliangut/sessionware
 * @author Julián Gutiérrez <juliangut@gmail.com>
 */

namespace Jgut\Middleware\Sessionware\Tests;

use Jgut\Middleware\Sessionware\Configuration;
use PHPUnit\Framework\TestCase;

/**
 * Configuration tests.
 */
class ConfigurationTest extends TestCase
{
    public function testDefaults()
    {
        $configuration = new Configuration();

        self::assertEquals(Configuration::SESSION_NAME_DEFAULT, $configuration->getName());
        self::assertEquals(sys_get_temp_dir(), $configuration->getSavePath());
        self::assertEquals(Configuration::LIFETIME_DEFAULT, $configuration->getLifetime());
        self::assertEquals(Configuration::TIMEOUT_KEY_DEFAULT, $configuration->getTimeoutKey());
        self::assertEquals('/', $configuration->getCookiePath());
        self::assertEquals('', $configuration->getCookieDomain());
        self::assertFalse($configuration->isCookieSecure());
        self::assertFalse($configuration->isCookieHttpOnly());
    }

    /**
     * @runInSeparateProcess
     */
    public function testFromEnvironment()
    {
        $configs = [
            'name'           => 'SESSION',
            'savePath'       => sys_get_temp_dir() . '/SESS',
            'lifetime'       => Configuration::LIFETIME_SHORT,
            'cookiePath'     => '/path',
            'cookieDomain'   => 'example.com',
            'cookieSecure'   => true,
            'cookieHttpOnly' => true,
        ];

        session_name($configs['name']);
        session_save_path($configs['savePath']);
        ini_set('session.gc_maxlifetime', Configuration::LIFETIME_SHORT);
        ini_set('session.cookie_lifetime', 0);
        ini_set('session.cookie_path', $configs['cookiePath']);
        ini_set('session.cookie_domain', $configs['cookieDomain']);
        ini_set('session.cookie_secure', $configs['cookieSecure']);
        ini_set('session.cookie_httponly', $configs['cookieHttpOnly']);

        $configuration = new Configuration();

        self::assertEquals($configs['name'], $configuration->getName());
        self::assertEquals($configs['savePath'], $configuration->getSavePath());
        self::assertEquals($configs['lifetime'], $configuration->getLifetime());
        self::assertEquals($configs['cookiePath'], $configuration->getCookiePath());
        self::assertEquals($configs['cookieDomain'], $configuration->getCookieDomain());
        self::assertTrue($configuration->isCookieSecure());
        self::assertTrue($configuration->isCookieHttpOnly());
    }

    public function testFromConfigurations()
    {
        $configs = [
            'name'           => 'SESSION',
            'savePath'       => sys_get_temp_dir() . '/SESS',
            'lifetime'       => Configuration::LIFETIME_SHORT,
            'timeoutKey'     => 'TIMEOUT',
            'cookiePath'     => '/path',
            'cookieDomain'   => 'example.com',
            'cookieSecure'   => true,
            'cookieHttpOnly' => true,
        ];

        $configuration = new Configuration($configs);

        self::assertEquals($configs['name'], $configuration->getName());
        self::assertEquals($configs['savePath'], $configuration->getSavePath());
        self::assertEquals($configs['lifetime'], $configuration->getLifetime());
        self::assertEquals($configs['timeoutKey'], $configuration->getTimeoutKey());
        self::assertEquals($configs['cookiePath'], $configuration->getCookiePath());
        self::assertEquals($configs['cookieDomain'], $configuration->getCookieDomain());
        self::assertTrue($configuration->isCookieSecure());
        self::assertTrue($configuration->isCookieHttpOnly());
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Session name must be a non empty string
     */
    public function testInvalidName()
    {
        new Configuration(['name' => ' ']);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Session save path must be a non empty string
     */
    public function testInvalidSavePath()
    {
        new Configuration(['savePath' => ' ']);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Session lifetime must be a positive integer
     */
    public function testInvalidLifetime()
    {
        new Configuration(['lifetime' => 0]);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Session timeout key must be a non empty string
     */
    public function testInvalidTimeoutKey()
    {
        new Configuration(['timeoutKey' => ' ']);
    }
}