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
use Jgut\Sessionware\Traits\NativeSessionTrait;

/**
 * Native PHP session handler.
 */
class Native extends \SessionHandler implements Handler
{
    use HandlerTrait;
    use FileHandlerTrait;
    use NativeSessionTrait;

    /**
     * @var bool
     */
    protected $useFilesHandler;

    /**
     * Native session handler constructor.
     */
    public function __construct()
    {
        $this->useFilesHandler = $this->getStringIniSetting('save_handler') === 'files';
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

        $savePath = $this->configuration->getSavePath();
        $sessionName = $this->configuration->getName();

        if ($this->useFilesHandler) {
            $savePath = $this->createSavePath($savePath, $sessionName);
        }

        return parent::open($savePath, $sessionName);
    }

    /**
     * {@inheritdoc}
     */
    public function read($sessionId)
    {
        $sessionData = parent::read($sessionId);

        return $sessionData ? $this->decryptSessionData($sessionData) : serialize([]);
    }

    /**
     * {@inheritdoc}
     */
    public function write($sessionId, $sessionData)
    {
        return parent::write($sessionId, $this->encryptSessionData($sessionData));
    }

    /**
     * {@inheritdoc}
     *
     * @SuppressWarnings(PMD.ShortMethodName)
     * @SuppressWarnings(PMD.UnusedFormalParameter)
     */
    public function gc($maxLifetime)
    {
        return parent::gc($this->configuration->getLifetime());
    }
}
