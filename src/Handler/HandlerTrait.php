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

use Defuse\Crypto\Crypto;
use Jgut\Sessionware\Configuration;

/**
 * Session handler utility trait.
 */
trait HandlerTrait
{
    /**
     * @var Configuration
     */
    protected $configuration;

    /**
     * Set configuration.
     *
     * @param Configuration $configuration
     *
     * @return static
     */
    public function setConfiguration(Configuration $configuration)
    {
        $this->configuration = $configuration;

        return $this;
    }

    /**
     * Checks if configuration is set.
     *
     * @throws \RuntimeException
     */
    protected function testConfiguration()
    {
        if ($this->configuration === null) {
            throw new \RuntimeException('Configuration must be set prior to use');
        }
    }

    /**
     * Encrypt session data based on configuration encryption key.
     *
     * @param string $plainData
     *
     * @return string
     */
    protected function encryptSessionData(string $plainData) : string
    {
        if (!$this->configuration->getEncryptionKey()) {
            return $plainData;
        }

        $encryptionKey = str_pad($this->configuration->getEncryptionKey(), 32, '=');

        return Crypto::encryptWithPassword($plainData, $encryptionKey);
    }

    /**
     * Decrypt session data based on configuration encryption key.
     *
     * @param string $encryptedData
     *
     * @return string
     */
    protected function decryptSessionData(string $encryptedData) : string
    {
        if (!$this->configuration->getEncryptionKey()) {
            return $encryptedData;
        }

        $encryptionKey = str_pad($this->configuration->getEncryptionKey(), 32, '=');

        return Crypto::decryptWithPassword($encryptedData, $encryptionKey);
    }
}
