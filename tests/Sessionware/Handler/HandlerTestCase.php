<?php

/*
 * sessionware (https://github.com/juliangut/sessionware).
 * PSR7 session management middleware.
 *
 * @license BSD-3-Clause
 * @link https://github.com/juliangut/sessionware
 * @author Julián Gutiérrez <juliangut@gmail.com>
 */

namespace Jgut\Middleware\Sessionware\Tests\Handler;

use Jgut\Middleware\Sessionware\Configuration;
use Jgut\Middleware\Sessionware\Tests\SessionTestCase;

/**
 * Session handler test case class.
 */
abstract class HandlerTestCase extends SessionTestCase
{
    /**
     * @var Configuration
     */
    protected $configuration;

    /**
     * {@inheritdoc}
     */
    public function setUp()
    {
        parent::setUp();

        $configuration = $this->getMockBuilder(Configuration::class)
            ->disableOriginalConstructor()
            ->getMock();
        $configuration
            ->expects(self::any())
            ->method('getName')
            ->will(self::returnValue('Sessionware'));
        $configuration
            ->expects(self::any())
            ->method('getLifetime')
            ->will(self::returnValue(Configuration::LIFETIME_EXTENDED));
        /* @var Configuration $configuration */

        $this->configuration = $configuration;
    }
}
