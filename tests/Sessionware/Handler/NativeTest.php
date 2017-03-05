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
use Jgut\Sessionware\Handler\Native;
use Jgut\Sessionware\Tests\SessionTestCase;

/**
 * Native PHP session handler test class.
 */
class NativeTest extends SessionTestCase
{
    /**
     * @var string
     */
    protected $savePath;

    /**
     * @var Native
     */
    protected $handler;

    /**
     * {@inheritdoc}
     */
    public function setUp()
    {
        if (ini_get('session.save_handler') !== 'files') {
            self::markTestSkipped('"session.save_handler" ini setting must be set to "files" to run this tests');
        }

        parent::setUp();

        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '1');
        ini_set('session.serialize_handler', 'php_serialize');

        $this->savePath = sys_get_temp_dir() . '/' . $this->sessionName;

        $this->configuration
            ->expects(self::any())
            ->method('getSavePath')
            ->will(self::returnValue(sys_get_temp_dir()));

        $handler = new Native();
        $handler->setConfiguration($this->configuration);

        $this->handler = $handler;
    }

    /**
     * {@inheritdoc}
     */
    public function tearDown()
    {
        if (!file_exists($this->savePath)) {
            return;
        }

        foreach (array_diff(scandir($this->savePath), ['.', '..']) as $file) {
            unlink($this->savePath . '/' . $file);
        }

        rmdir($this->savePath);
    }

    /**
     * @runInSeparateProcess
     */
    public function testOpen()
    {
        session_register_shutdown();
        session_set_save_handler($this->handler, false);

        session_start();
        session_abort();

        self::assertFileExists($this->savePath);
        self::assertFileExists($this->savePath . '/sess_' . $this->sessionId);
    }

    /**
     * @runInSeparateProcess
     */
    public function testAccessors()
    {
        session_register_shutdown();
        session_set_save_handler($this->handler, false);

        session_start();

        $_SESSION['sessionKey'] = 'sessionValue';

        session_write_close();

        $_SESSION = null;

        session_start();

        self::assertEquals('sessionValue', $_SESSION['sessionKey']);
    }
}
