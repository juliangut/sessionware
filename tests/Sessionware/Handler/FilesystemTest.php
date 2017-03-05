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
use Jgut\Sessionware\Handler\Filesystem;
use Jgut\Sessionware\Tests\SessionTestCase;
use org\bovigo\vfs\vfsStream;

/**
 * Filesystem session handler test class.
 */
class FilesystemTest extends SessionTestCase
{
    /**
     * @var \org\bovigo\vfs\vfsStreamDirectory
     */
    protected $fileSystem;

    /**
     * @var string
     */
    protected $savePath;

    /**
     * @var \Jgut\Sessionware\Handler\Handler
     */
    protected $handler;

    /**
     * {@inheritdoc}
     */
    public function setUp()
    {
        parent::setUp();

        $this->fileSystem = vfsStream::setup('root', 0777);
        $this->savePath = $this->fileSystem->url() . '/' . $this->sessionName;

        $this->configuration
            ->expects(self::any())
            ->method('getSavePath')
            ->will(self::returnValue($this->fileSystem->url()));

        $handler = new Filesystem('session_');
        $handler->setConfiguration($this->configuration);

        $this->handler = $handler;
    }

    public function testOpenClose()
    {
        $this->handler->open('not', 'used');
        $this->handler->close();

        self::assertFileExists($this->savePath);
    }

    public function testRead()
    {
        $sessionFile = $this->savePath . '/session_' . $this->sessionId;

        $this->handler->open('not', 'used');
        $this->handler->read($this->sessionId);

        self::assertFileExists($sessionFile);
        self::assertEquals(serialize([]), $this->handler->read($this->sessionId));
        self::assertStringEqualsFile($sessionFile, serialize([]));
    }

    public function testWrite()
    {
        $sessionData = serialize(['sessionData' => 'data']);
        $sessionFile = $this->savePath . '/session_' . $this->sessionId;

        $this->handler->open('not', 'used');
        $this->handler->write($this->sessionId, $sessionData);

        self::assertFileExists($sessionFile);
        self::assertStringEqualsFile($sessionFile, $sessionData);
    }

    public function testDestroy()
    {
        $sessionFile = $this->savePath . '/session_' . $this->sessionId;
        mkdir($this->savePath);
        touch($sessionFile);

        $this->handler->open('not', 'used');
        $this->handler->destroy($this->sessionId);

        self::assertFileNotExists($sessionFile);
    }

    public function testGarbageCollector()
    {
        $modTime = time() - (Configuration::LIFETIME_EXTENDED * 2);

        $fileSystem = vfsStream::setup('root', 0777, [$this->sessionName => []]);
        $sessionSavePath = $fileSystem->getChild($this->sessionName);

        $sessionSavePath->addChild(
            vfsStream::newFile('session_' . $this->sessionId)->lastModified($modTime)
        );

        $this->handler->open('not', 'used');
        $this->handler->gc(0);

        self::assertEmpty($sessionSavePath->getChildren());
    }
}
