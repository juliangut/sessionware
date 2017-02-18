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

namespace Jgut\Sessionware\Handler;

use Jgut\Sessionware\Configuration;
use Jgut\Sessionware\Traits\NativeSessionTrait;

/**
 * Native PHP session handler.
 */
class Native extends \SessionHandler implements Handler
{
    use HandlerTrait;
    use NativeSessionTrait;

    /**
     * @var bool
     */
    protected $isFileHandler;

    /**
     * Native session handler constructor.
     */
    public function __construct()
    {
        $this->isFileHandler = $this->getStringIniSetting('save_handler') === 'files';
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

        if ($this->isFileHandler &&
            (!is_dir($savePath) && !@mkdir($savePath, 0777, true) && !is_dir($savePath))
        ) {
            // @codeCoverageIgnoreStart
            throw new \RuntimeException(
                sprintf('Failed to create session save path "%s", directory might be write protected', $savePath)
            );
            // @codeCoverageIgnoreEnd
        }

        return parent::open($savePath, $sessionName);
    }

    /**
     * {@inheritdoc}
     *
     * @SuppressWarnings(PMD.ShortMethodName)
     */
    public function gc($maxLifetime)
    {
        return parent::gc($this->configuration->getLifetime());
    }
}
