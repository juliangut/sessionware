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
use Jgut\Sessionware\Handler\File;
use Jgut\Sessionware\Tests\SessionTestCase;
use org\bovigo\vfs\vfsStream;

/**
 * File session handler test class.
 */
class FileTest extends SessionTestCase
{
    /**
     * @var \Jgut\Sessionware\Handler\Handler
     */
    protected $handler;

    /**
     * @var \org\bovigo\vfs\vfsStreamDirectory
     */
    protected $fileSystem;

    /**
     * @var string
     */
    protected $savePath;

    /**
     * {@inheritdoc}
     */
    public function setUp()
    {
        $this->fileSystem = vfsStream::setup('root', 0777);

        $this->savePath = $this->fileSystem->url() . '/Sessionware';

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
            ->will(self::returnValue($this->fileSystem->url()));
        $configuration
            ->expects(self::any())
            ->method('getLifetime')
            ->will(self::returnValue(Configuration::LIFETIME_EXTENDED));
        /* @var Configuration $configuration */

        $handler = new File('session_');
        $handler->setConfiguration($configuration);

        $this->handler = $handler;
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Configuration must be set prior to use
     */
    public function testNoConfiguration()
    {
        $handler = new File();

        $handler->open($this->fileSystem->url(), Configuration::SESSION_NAME_DEFAULT);
    }

    public function testOpenClose()
    {
        $this->handler->open('not', 'used');
        $this->handler->close();

        self::assertFileExists($this->savePath);
    }

    public function testRead()
    {
        $sessionId = str_repeat('0', 32);
        $sessionFile = $this->savePath . '/session_' . $sessionId;

        $this->handler->open('not', 'used');
        $this->handler->read($sessionId);

        self::assertFileExists($sessionFile);

        $sessionData = $this->handler->read($sessionId);
        self::assertEquals('a:0:{}', $sessionData);
        self::assertEquals('a:0:{}', file_get_contents($sessionFile));
    }

    public function testWrite()
    {
        $sessionId = str_repeat('0', 32);
        $sessionData = serialize(['sessionData' => 'data']);
        $sessionFile = $this->savePath . '/session_' . $sessionId;

        $this->handler->open('not', 'used');
        $this->handler->write($sessionId, $sessionData);

        self::assertFileExists($this->savePath . '/session_' . $sessionId);
        self::assertEquals($sessionData, file_get_contents($sessionFile));
    }

    public function testGc()
    {
        $modTime = time() - (Configuration::LIFETIME_EXTENDED * 2);

        $fileSystem = vfsStream::setup('root', 0777, ['Sessionware' => []]);
        $sessionSavePath = $fileSystem->getChild('Sessionware');

        $sessionSavePath->addChild(
            vfsStream::newFile('session_' . str_repeat('1', 32))->lastModified($modTime)
        );

        $this->handler->open('not', 'used');
        $this->handler->gc(0);

        self::assertEmpty($sessionSavePath->getChildren());
    }

    public function testDestroy()
    {
        $sessionId = str_repeat('0', 32);
        $sessionFile = $this->savePath . '/session_' . $sessionId;

        $this->handler->open('not', 'used');

        $this->handler->read($sessionId);
        $this->handler->destroy($sessionId);

        self::assertFileNotExists($sessionFile);
    }
}
