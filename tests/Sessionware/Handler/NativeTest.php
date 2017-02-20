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
     * @var \Jgut\Sessionware\Handler\Handler
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

        $this->handler = new Native();
    }

    /**
     * @runInSeparateProcess
     *
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Configuration must be set prior to use
     */
    public function testNoConfiguration()
    {
        $this->handler->open(sys_get_temp_dir(), Configuration::SESSION_NAME_DEFAULT);
    }

    /**
     * @runInSeparateProcess
     */
    public function testUse()
    {
        $savePath = sys_get_temp_dir() . '/Sessionware';

        $this->removeDir($savePath);

        $configuration = $this->getMockBuilder(Configuration::class)
            ->disableOriginalConstructor()
            ->getMock();
        $configuration
            ->expects(self::any())
            ->method('getName')
            ->will(self::returnValue('Sessionware'));
        $configuration
            ->expects(self::any())
            ->method('getSavePath')
            ->will(self::returnValue(sys_get_temp_dir()));
        /* @var Configuration $configuration */

        $this->handler->setConfiguration($configuration);

        session_register_shutdown();
        session_set_save_handler($this->handler, false);

        session_start();

        $_SESSION['sessionKey'] = 'sessionValue';

        self::assertFileExists($savePath);
        self::assertFileExists($savePath . '/sess_' . str_repeat('0', 32));

        session_write_close();

        $_SESSION = null;

        session_start();

        self::assertFileExists($savePath);
        self::assertFileExists($savePath . '/sess_' . str_repeat('0', 32));
        self::assertEquals('sessionValue', $_SESSION['sessionKey']);

        $this->removeDir($savePath);
    }

    /**
     * Recursive directory removal.
     *
     * @param string $path
     */
    private function removeDir($path)
    {
        if (!file_exists($path)) {
            return;
        }

        foreach (array_diff(scandir($path), ['.', '..']) as $file) {
            $file = $path . '/' . $file;

            if (is_dir($file)) {
                $this->removeDir($file);
            } else {
                unlink($file);
            }
        }

        rmdir($path);
    }
}
