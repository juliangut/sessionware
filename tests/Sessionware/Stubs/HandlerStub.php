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

namespace Jgut\Sessionware\Tests\Stubs;

use Jgut\Sessionware\Traits\HandlerTrait;

/**
 * Session handler utility trait test case class.
 */
class HandlerStub
{
    use HandlerTrait {
        HandlerTrait::testConfiguration as traitTestConfiguration;
    }

    /**
     * @throws \RuntimeException
     */
    public function testConfiguration()
    {
        $this->traitTestConfiguration();
    }

    /**
     * @param string $plainData
     *
     * @return string
     */
    public function encryptData(string $plainData) : string
    {
        return $this->encryptSessionData($plainData);
    }

    /**
     * @param string $encryptedData
     *
     * @return string
     */
    public function decryptData(string $encryptedData): string
    {
        return $this->decryptSessionData($encryptedData);
    }
}
