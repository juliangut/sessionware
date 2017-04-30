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

namespace Jgut\Sessionware\Handler;

use Jgut\Sessionware\Traits\FileHandlerTrait;
use Jgut\Sessionware\Traits\HandlerTrait;

/**
 * Filesystem session handler.
 */
class Filesystem implements Handler
{
    use HandlerTrait;
    use FileHandlerTrait;

    /**
     * @var string
     */
    protected $savePath;

    /**
     * @var string
     */
    protected $filePrefix;

    /**
     * File session handler constructor.
     *
     * @param string $filePrefix
     */
    public function __construct($filePrefix = 'sess_')
    {
        $this->filePrefix = $filePrefix;
    }

    /**
     * {@inheritdoc}
     *
     * @throws \RuntimeException
     *
     * @SuppressWarnings(PMD.UnusedFormalParameter)
     */
    public function open($savePath, $sessionName)
    {
        $this->testConfiguration();

        $this->savePath = $this->createSavePath($this->configuration->getSavePath(), $this->configuration->getName());

        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @return bool
     */
    public function close()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @return bool
     */
    public function destroy($sessionId)
    {
        $sessionFile = $this->getSessionFile($sessionId);

        if (file_exists($sessionFile)) {
            return unlink($sessionFile);
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function read($sessionId)
    {
        $sessionFile = $this->getSessionFile($sessionId);

        if (!file_exists($sessionFile)) {
            $sessionData = serialize([]);

            $this->write($sessionId, $sessionData);

            return $sessionData;
        }

        $fileDescriptor = fopen($sessionFile, 'rb');
        flock($fileDescriptor, \LOCK_SH);

        $sessionData = stream_get_contents($fileDescriptor);

        flock($fileDescriptor, \LOCK_UN);
        fclose($fileDescriptor);

        return $this->decryptSessionData($sessionData);
    }

    /**
     * {@inheritdoc}
     */
    public function write($sessionId, $sessionData)
    {
        $sessionFile = $this->getSessionFile($sessionId);
        if (!file_exists($sessionFile)) {
            touch($sessionFile);
            chmod($sessionFile, 0777);
        }

        // file_put_contents locking does not support user-land stream wrappers (https://bugs.php.net/bug.php?id=72950)
        $fileDescriptor = fopen($sessionFile, 'w');
        flock($fileDescriptor, \LOCK_EX);

        fwrite($fileDescriptor, $this->encryptSessionData($sessionData));

        flock($fileDescriptor, \LOCK_UN);
        fclose($fileDescriptor);

        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @SuppressWarnings(PMD.ShortMethodName)
     * @SuppressWarnings(PMD.UnusedFormalParameter)
     */
    public function gc($maxLifetime)
    {
        $currentTime = time();

        foreach (new \DirectoryIterator($this->savePath) as $sessionFile) {
            if ($sessionFile->isFile()
                && strpos($sessionFile->getFilename(), $this->filePrefix) === 0
                && $this->configuration->getLifetime() < ($currentTime - $sessionFile->getMTime())
            ) {
                unlink($sessionFile->getPathname());
            }
        }

        return true;
    }

    /**
     * Get session file.
     *
     * @param string $sessionId
     *
     * @return string
     */
    protected function getSessionFile(string $sessionId): string
    {
        return $this->savePath . DIRECTORY_SEPARATOR . $this->filePrefix . $sessionId;
    }
}
