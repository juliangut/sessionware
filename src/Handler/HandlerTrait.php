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

use Defuse\Crypto\Crypto;
use Defuse\Crypto\Exception\CryptoException;
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
     * @return self
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
     * @throws CryptoException
     *
     * @return string
     */
    protected function encryptSessionData(string $plainData) : string
    {
        if (!$this->configuration->getEncryptionKey()) {
            return $plainData;
        }

        return Crypto::encrypt($plainData, $this->configuration->getEncryptionKey());
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
        if ($encryptedData === '') {
            return serialize([]);
        }

        $plainData = $encryptedData;

        if ($this->configuration->getEncryptionKey()) {
            try {
                $plainData = Crypto::decrypt($encryptedData, $this->configuration->getEncryptionKey());
            } catch (CryptoException $exception) {
                // Ignore error and treat as empty session
                return serialize([]);
            }
        }

        return $plainData === 'b:0;' || @unserialize($plainData) !== false ? $plainData : serialize([]);
    }
}
