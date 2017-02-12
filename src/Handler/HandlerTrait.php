<?php

/*
 * sessionware (https://github.com/juliangut/sessionware).
 * PSR7 session management middleware.
 *
 * @license BSD-3-Clause
 * @link https://github.com/juliangut/sessionware
 * @author Julián Gutiérrez <juliangut@gmail.com>
 */

namespace Jgut\Middleware\Sessionware\Handler;

use Jgut\Middleware\Sessionware\Configuration;

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
}