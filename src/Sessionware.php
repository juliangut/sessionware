<?php

/*
 * sessionware (https://github.com/juliangut/sessionware).
 * PSR7 session management middleware.
 *
 * @license BSD-3-Clause
 * @link https://github.com/juliangut/sessionware
 * @author Julián Gutiérrez <juliangut@gmail.com>
 */

namespace Jgut\Middleware\Sessionware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * PHP session handler middleware.
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class Sessionware
{
    use SessionIniSettingTrait;

    /**
     * @var Configuration
     */
    protected $configuration;

    /**
     * Middleware constructor.
     *
     * @param Configuration|null $configuration
     *
     * @throws \RuntimeException
     */
    public function __construct(Configuration $configuration = null)
    {
        if (session_status() === PHP_SESSION_DISABLED) {
            // @codeCoverageIgnoreStart
            throw new \RuntimeException('PHP sessions are disabled');
            // @codeCoverageIgnoreEnd
        }

        if (!$this->isCli()) {
            $this->verifyIniSettings();
        }

        if ($configuration === null) {
            $configuration = new Configuration();
        }

        $this->configuration = $configuration;
    }

    /**
     * Check if running on CLI.
     *
     * @return bool
     */
    protected function isCli()
    {
        return php_sapi_name() === 'cli';
    }

    /**
     * Verify session ini settings.
     *
     * @throws \RuntimeException
     *
     * @codeCoverageIgnore
     */
    final protected function verifyIniSettings()
    {
        if ((bool) $this->getSessionIniSetting('auto_start') !== false) {
            throw new \RuntimeException('"session.auto_start" ini setting must be set to false');
        }

        if ((bool) $this->getSessionIniSetting('use_trans_sid') !== false) {
            throw new \RuntimeException('"session.use_trans_sid" ini setting must be set to false');
        }

        if ((bool) $this->getSessionIniSetting('use_cookies') !== true) {
            throw new \RuntimeException('"session.use_cookies" ini setting must be set to true');
        }

        if ((bool) $this->getSessionIniSetting('use_only_cookies') !== true) {
            throw new \RuntimeException('"session.use_only_cookies" ini setting must be set to true');
        }

        if ((bool) $this->getSessionIniSetting('use_strict_mode') !== false) {
            throw new \RuntimeException('"session.use_strict_mode" ini setting must be set to false');
        }

        if ($this->getSessionIniSetting('cache_limiter') !== null) {
            throw new \RuntimeException('"session.cache_limiter" ini setting must be set to empty string');
        }
    }

    /**
     * Execute the middleware.
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @param callable               $next
     *
     * @throws \RuntimeException
     *
     * @return ResponseInterface
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $next)
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            throw new \RuntimeException('Session has already been started');
        }

        $this->configureSession($request);

        $response = $next($request->withAttribute('session', new Session($this->configuration)), $response);

        return $this->respondWithSessionCookie($response);
    }

    /**
     * Configure session settings.
     *
     * @param ServerRequestInterface $request
     *
     * @throws \RuntimeException
     */
    protected function configureSession(ServerRequestInterface $request)
    {
        $this->configureSessionSerializer();
        $this->configureSessionSavePath();
        $this->configureSessionTimeout();
        $this->configureSessionId($request);
    }

    /**
     * Configure session serialize handler.
     */
    protected function configureSessionSerializer()
    {
        // Use better session serializer when available
        if ($this->getSessionIniSetting('serialize_handler') !== 'php_serialize'
            && version_compare(PHP_VERSION, '5.5.4', '>=')
        ) {
            // @codeCoverageIgnoreStart
            $this->setSessionIniSetting('serialize_handler', 'php_serialize');
            // @codeCoverageIgnoreEnd
        }
    }

    /**
     * Configure session save path if using default PHP session save handler.
     *
     * @throws \RuntimeException
     */
    protected function configureSessionSavePath()
    {
        if ($this->getSessionIniSetting('save_handler') !== 'files') {
            // @codeCoverageIgnoreStart
            return;
            // @codeCoverageIgnoreEnd
        }

        $sessionName = $this->configuration->getName();
        $savePath = $this->configuration->getSavePath();

        $savePathParts = explode(DIRECTORY_SEPARATOR, rtrim($savePath, DIRECTORY_SEPARATOR));
        if ($sessionName !== Configuration::SESSION_NAME_DEFAULT && $sessionName !== array_pop($savePathParts)) {
            $savePath .= DIRECTORY_SEPARATOR . $sessionName;
        }

        if ($savePath === sys_get_temp_dir() || $this->getSessionIniSetting('save_path') === $savePath) {
            return;
        }

        if (!@mkdir($savePath, 0775, true) && (!is_dir($savePath) || !is_writable($savePath)))
        {
            throw new \RuntimeException(
                sprintf('Failed to create session save path at "%s", directory might not be write enabled', $savePath)
            );
        }

        $this->setSessionIniSetting('save_path', $savePath);
    }

    /**
     * Configure session timeout.
     */
    protected function configureSessionTimeout()
    {
        // Signal garbage collector with defined timeout
        $this->setSessionIniSetting('gc_maxlifetime', $this->configuration->getLifetime());
    }

    /**
     * Configure session identifier.
     *
     * @param ServerRequestInterface $request
     */
    protected function configureSessionId(ServerRequestInterface $request)
    {
        $requestCookies = $request->getCookieParams();
        $sessionName = $this->configuration->getName();

        if (array_key_exists($sessionName, $requestCookies) && !empty($requestCookies[$sessionName])) {
            session_id($requestCookies[$sessionName]);
        }
    }

    /**
     * Add session cookie Set-Cookie header to response.
     *
     * @param ResponseInterface $response
     *
     * @throws \InvalidArgumentException
     *
     * @return ResponseInterface
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    protected function respondWithSessionCookie(ResponseInterface $response)
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            // @codeCoverageIgnoreStart
            return $response;
            // @codeCoverageIgnoreEnd
        }

        if (strlen(session_id()) !== Configuration::SESSION_ID_LENGTH) {
            // $this->recreateSession();
        }

        $timeoutKey = $this->configuration->getTimeoutKey();
        $expireTime = array_key_exists($timeoutKey, $_SESSION)
            ? $_SESSION[$timeoutKey]
            : time() - $this->configuration->getLifetime();

        $cookieParams = [
            sprintf(
                'expires=%s; max-age=%s',
                gmdate('D, d M Y H:i:s T', $expireTime),
                $this->configuration->getLifetime()
            ),
        ];

        if (!empty($this->configuration->getCookiePath())) {
            $cookieParams[] = 'path=' . $this->configuration->getCookiePath();
        }

        if (!empty($this->configuration->getCookieDomain())) {
            $cookieParams[] = 'domain=' . $this->configuration->getCookieDomain();
        }

        if ((bool) $this->configuration->isCookieSecure()) {
            $cookieParams[] = 'secure';
        }

        if ((bool) $this->configuration->isCookieHttpOnly()) {
            $cookieParams[] = 'httponly';
        }

        return $response->withAddedHeader(
            'Set-Cookie',
            sprintf(
                '%s=%s; %s',
                urlencode($this->configuration->getName()),
                urlencode(session_id()),
                implode('; ', $cookieParams)
            )
        );
    }
}
