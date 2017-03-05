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

use Jgut\Sessionware\Configuration;

/**
 * File session handler.
 */
class File implements Handler
{
    use HandlerTrait;

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
     */
    public function open($savePath, $sessionName)
    {
        $this->testConfiguration();

        $savePath = $this->configuration->getSavePath();
        $sessionName = $this->configuration->getName();

        $savePathParts = explode(DIRECTORY_SEPARATOR, rtrim($savePath, DIRECTORY_SEPARATOR));
        if ($sessionName !== Configuration::SESSION_NAME_DEFAULT && $sessionName !== array_pop($savePathParts)) {
            $savePath .= DIRECTORY_SEPARATOR . $sessionName;
        }

        if (!is_dir($savePath) && !@mkdir($savePath, 0777, true) && !is_dir($savePath)) {
            // @codeCoverageIgnoreStart
            throw new \RuntimeException(
                sprintf('Failed to create session save path "%s", directory might be write protected', $savePath)
            );
            // @codeCoverageIgnoreEnd
        }

        $this->savePath = $savePath;

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
            unlink($sessionFile);
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
    protected function getSessionFile(string $sessionId) : string
    {
        return $this->savePath . DIRECTORY_SEPARATOR . $this->filePrefix . $sessionId;
    }
}
