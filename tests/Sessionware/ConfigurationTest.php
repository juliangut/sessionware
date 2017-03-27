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

namespace Jgut\Sessionware\Tests;

use Defuse\Crypto\Key;
use Jgut\Sessionware\Configuration;
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
        self::assertEquals('/', $configuration->getCookiePath());
        self::assertEquals('', $configuration->getCookieDomain());
        self::assertFalse($configuration->isCookieSecure());
        self::assertFalse($configuration->isCookieHttpOnly());
        self::assertNull($configuration->getEncryptionKey());
        self::assertEquals(Configuration::TIMEOUT_KEY_DEFAULT, $configuration->getTimeoutKey());
    }

    /**
     * @runInSeparateProcess
     */
    public function testFromEnvironment()
    {
        $configs = [
            'name' => 'SESSION',
            'savePath' => sys_get_temp_dir() . '/SESS',
            'lifetime' => Configuration::LIFETIME_SHORT,
            'cookiePath' => '/path',
            'cookieDomain' => 'example.com',
            'cookieSecure' => true,
            'cookieHttpOnly' => true,
        ];

        session_name($configs['name']);
        session_save_path($configs['savePath']);
        ini_set('session.gc_maxlifetime', (string) Configuration::LIFETIME_SHORT);
        ini_set('session.cookie_lifetime', '0');
        ini_set('session.cookie_path', $configs['cookiePath']);
        ini_set('session.cookie_domain', $configs['cookieDomain']);
        ini_set('session.cookie_secure', (string) $configs['cookieSecure']);
        ini_set('session.cookie_httponly', (string) $configs['cookieHttpOnly']);

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
            'name' => 'SESSION',
            'savePath' => sys_get_temp_dir() . '/SESS',
            'lifetime' => Configuration::LIFETIME_SHORT,
            'cookiePath' => '/path',
            'cookieDomain' => 'example.com',
            'cookieSecure' => true,
            'cookieHttpOnly' => true,
            'cookieSameSite' => Configuration::SAME_SITE_STRICT,
            'timeoutKey' => '__CUSTOM_TIMEOUT__',
            'encryptionKey' => 'def000005672fe08126322d5868e2695aff84cade023d6e776986bcd6137ee6423cf' .
                               'ce4b3c9e11621305bb3d84d1c45382ba35b4c993d9f287ca4fcd2c0155b1a1ffbe42',
        ];

        $configuration = new Configuration($configs);

        self::assertEquals($configs['name'], $configuration->getName());
        self::assertEquals($configs['savePath'], $configuration->getSavePath());
        self::assertEquals($configs['lifetime'], $configuration->getLifetime());
        self::assertEquals($configs['cookiePath'], $configuration->getCookiePath());
        self::assertEquals($configs['cookieDomain'], $configuration->getCookieDomain());
        self::assertTrue($configuration->isCookieSecure());
        self::assertTrue($configuration->isCookieHttpOnly());
        self::assertEquals($configs['cookieSameSite'], $configuration->getCookieSameSite());
        self::assertInstanceOf(Key::class, $configuration->getEncryptionKey());
        self::assertEquals($configs['timeoutKey'], $configuration->getTimeoutKey());
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Session name must be a non empty valid string
     */
    public function testInvalidName()
    {
        new Configuration(['name' => '=//not_valid_session_name//=']);
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
     * @expectedExceptionMessage "unknown" is not a valid cookie SameSite restriction value
     */
    public function testInvalidCookieSameSite()
    {
        new Configuration(['cookieSameSite' => 'unknown']);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Encryption key must be a string or an instance of Defuse\Crypto\Key. integer given
     */
    public function testInvalidEncryptionKey()
    {
        new Configuration(['encryptionKey' => 100]);
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
