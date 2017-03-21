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

namespace Jgut\Sessionware\Traits;

use Jgut\Sessionware\Configuration;

/**
 * File handler helper trait.
 */
trait FileHandlerTrait
{
    /**
     * Create session save path.
     *
     * @param string $savePath
     * @param string $sessionName
     *
     * @throws \RuntimeException
     *
     * @return string
     */
    protected function createSavePath(string $savePath, string $sessionName): string
    {
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

        return $savePath;
    }
}
