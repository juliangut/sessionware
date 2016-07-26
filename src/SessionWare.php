<?php
/**
 * SessionWare (https://github.com/juliangut/sessionware)
 * PSR7 session management middleware
 *
 * @license BSD-3-Clause
 * @author Julián Gutiérrez <juliangut@gmail.com>
 */

namespace Jgut\Middleware;

use League\Event\EmitterAwareInterface;
use League\Event\EmitterTrait;
use League\Event\Event;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * PHP session handler middleware.
 */
class SessionWare implements EmitterAwareInterface
{
    use EmitterTrait;

    const SESSION_LIFETIME_FLASH    = 300; // 5 minutes
    const SESSION_LIFETIME_SHORT    = 600; // 10 minutes
    const SESSION_LIFETIME_NORMAL   = 900; // 15 minutes
    const SESSION_LIFETIME_DEFAULT  = 1440; // 24 minutes
    const SESSION_LIFETIME_EXTENDED = 3600; // 1 hour
    const SESSION_LIFETIME_INFINITE = PHP_INT_MAX; // Around 1145 years (x86_64)

    const SESSION_KEY = 'session';
    const TIMEOUT_CONTROL_KEY = '__SESSIONWARE_TIMEOUT_TIMESTAMP__';

    /**
     * @var array
     */
    protected $settings;

    /**
     * @var array
     */
    protected $initialSessionParams;

    /**
     * @var string
     */
    protected $sessionName;

    /**
     * @var int
     */
    protected $sessionLifetime;

    /**
     * @var string
     */
    protected $sessionTimeoutKey;

    /**
     * Middleware constructor.
     *
     * @param array $settings
     * @param array $initialSessionParams
     */
    public function __construct(array $settings = [], array $initialSessionParams = [])
    {
        $this->settings = $settings;

        $this->initialSessionParams = $initialSessionParams;
    }

    /**
     * Execute the middleware.
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @param callable               $next
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     *
     * @return ResponseInterface
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $next)
    {
        $this->startSession($request);

        $response = $next($request, $response);

        return $this->respondWithSessionCookie($response);
    }

    /**
     * Configure session settings.
     *
     * @param ServerRequestInterface $request
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    protected function startSession(ServerRequestInterface $request)
    {
        if (session_status() === PHP_SESSION_DISABLED) {
            // @codeCoverageIgnoreStart
            throw new \RuntimeException('PHP sessions are disabled');
            // @codeCoverageIgnoreEnd
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            throw new \RuntimeException('Session has already been started, review "session.auto_start" ini setting');
        }

        $sessionSettings = $sessionSettings = $this->getSessionSettings($this->settings);

        // First configure session name
        $this->configureSessionName($sessionSettings);

        $this->configureSessionCookies($sessionSettings);
        $this->configureSessionSavePath($sessionSettings);
        $this->configureSessionTimeout($sessionSettings);
        $this->configureSessionSerializer();
        $this->configureSessionHeaders();

        $this->configureSessionId($request);

        session_start();

        if (session_status() !== PHP_SESSION_ACTIVE) {
            // @codeCoverageIgnoreStart
            throw new \RuntimeException('Session could not be started');
            // @codeCoverageIgnoreEnd
        }

        $this->manageSessionTimeout();

        $this->populateSession($this->initialSessionParams);
    }

    /**
     * Retrieve default session parameters.
     *
     * @param array $customSettings
     *
     * @return array
     */
    protected function getSessionSettings(array $customSettings)
    {
        $lifeTime = (int) $this->getSessionSetting('cookie_lifetime') === 0
            ? (int) $this->getSessionSetting('gc_maxlifetime')
            : min($this->getSessionSetting('cookie_lifetime'), (int) $this->getSessionSetting('gc_maxlifetime'));

        $defaultSettings = [
            'name'             => $this->getSessionSetting('name', 'PHPSESSID'),
            'path'             => $this->getSessionSetting('cookie_path'),
            'domain'           => $this->getSessionSetting('cookie_domain', '/'),
            'secure'           => $this->getSessionSetting('cookie_secure'),
            'httponly'         => $this->getSessionSetting('cookie_httponly'),
            'savePath'         => $this->getSessionSetting('save_path', sys_get_temp_dir()),
            'lifetime'         => $lifeTime > 0 ? $lifeTime : static::SESSION_LIFETIME_DEFAULT,
            'sessionKey'       => static::SESSION_KEY,
            'timeoutKey'       => static::TIMEOUT_CONTROL_KEY,
        ];

        return array_merge($defaultSettings, $customSettings);
    }

    /**
     * Configure session name.
     *
     * @throws \InvalidArgumentException
     *
     * @param array $settings
     */
    protected function configureSessionName(array $settings)
    {
        if (trim($settings['name']) === '') {
            throw new \InvalidArgumentException('Session name must be a non empty string');
        }

        $this->sessionName = trim($settings['name']);

        $this->setSessionSetting('name', $this->sessionName);
    }

    /**
     * Configure session cookies parameters.
     *
     * @param array $settings
     */
    protected function configureSessionCookies(array $settings)
    {
        $this->setSessionSetting('cookie_path', $settings['path']);
        $this->setSessionSetting('cookie_domain', $settings['domain']);
        $this->setSessionSetting('cookie_secure', $settings['secure']);
        $this->setSessionSetting('cookie_httponly', $settings['httponly']);
    }

    /**
     * Configure session save path if using default PHP session save handler.
     *
     * @param array $settings
     *
     * @throws \RuntimeException
     */
    protected function configureSessionSavePath(array $settings)
    {
        if ($this->getSessionSetting('save_handler') !== 'files') {
            // @codeCoverageIgnoreStart
            return;
            // @codeCoverageIgnoreEnd
        }

        $savePath = trim($settings['savePath']);
        if ($savePath === '') {
            $savePath = sys_get_temp_dir();
        }

        $savePathParts = explode(DIRECTORY_SEPARATOR, rtrim($savePath, DIRECTORY_SEPARATOR));
        if ($this->sessionName !== 'PHPSESSID' && $this->sessionName !== array_pop($savePathParts)) {
            $savePath .= DIRECTORY_SEPARATOR . $this->sessionName;
        }

        if ($savePath !== sys_get_temp_dir()
            && !@mkdir($savePath, 0775, true) && (!is_dir($savePath) || !is_writable($savePath))
        ) {
            throw new \RuntimeException(
                sprintf('Failed to create session save path "%s", or directory is not writable', $savePath)
            );
        }

        $this->setSessionSetting('save_path', $savePath);
    }

    /**
     * Configure session timeout.
     *
     * @param array $settings
     *
     * @throws \InvalidArgumentException
     */
    protected function configureSessionTimeout(array $settings)
    {
        $lifetime = (int) $settings['lifetime'];

        if ($lifetime < 1) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a valid session lifetime', $lifetime));
        }

        $this->sessionLifetime = $lifetime;

        $timeoutKey = trim($settings['timeoutKey']);
        if ($timeoutKey === '') {
            throw new \InvalidArgumentException(
                sprintf('"%s" is not a valid session timeout control key name', $settings['timeoutKey'])
            );
        }

        $this->sessionTimeoutKey = $timeoutKey;

        // Signal garbage collector with defined timeout
        $this->setSessionSetting('gc_maxlifetime', $lifetime);
    }

    /**
     * Configure session serialize handler.
     */
    protected function configureSessionSerializer()
    {
        // Use better session serializer when available
        if ($this->getSessionSetting('serialize_handler') === 'php' && version_compare(PHP_VERSION, '5.5.4', '>=')) {
            // @codeCoverageIgnoreStart
            $this->setSessionSetting('serialize_handler', 'php_serialize');
            // @codeCoverageIgnoreEnd
        }
    }

    /**
     * Prevent headers from being automatically sent to client on session start.
     */
    protected function configureSessionHeaders()
    {
        $this->setSessionSetting('use_trans_sid', false);
        $this->setSessionSetting('use_cookies', true);
        $this->setSessionSetting('use_only_cookies', true);
        $this->setSessionSetting('use_strict_mode', false);
        $this->setSessionSetting('cache_limiter', '');
    }

    /**
     * Configure session identifier.
     *
     * @param ServerRequestInterface $request
     */
    protected function configureSessionId(ServerRequestInterface $request)
    {
        $requestCookies = $request->getCookieParams();

        $sessionId = array_key_exists($this->sessionName, $requestCookies)
            && trim($requestCookies[$this->sessionName]) !== ''
                ? trim($requestCookies[$this->sessionName])
                : session_id();

        if (trim($sessionId) === '') {
            $sessionId = static::generateSessionId();
        }

        session_id($sessionId);
    }

    /**
     * Manage session timeout.
     *
     * @throws \InvalidArgumentException
     */
    protected function manageSessionTimeout()
    {
        if (array_key_exists($this->sessionTimeoutKey, $_SESSION) && $_SESSION[$this->sessionTimeoutKey] < time()) {
            $this->emit(Event::named('pre.session_timeout'), session_id());

            $_SESSION = [];
            session_unset();
            session_destroy();

            session_id(static::generateSessionId());

            session_start();

            $this->emit(Event::named('post.session_timeout'), session_id());
        }

        $_SESSION[$this->sessionTimeoutKey] = time() + $this->sessionLifetime;
    }

    /**
     * Populate session with initial parameters if they don't exist.
     *
     * @param array $initialSessionParams
     */
    protected function populateSession(array $initialSessionParams)
    {
        foreach ($initialSessionParams as $parameter => $value) {
            if (!array_key_exists($parameter, $_SESSION)) {
                $_SESSION[$parameter] = $value;
            }
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
     */
    protected function respondWithSessionCookie(ResponseInterface $response)
    {
        if (session_status() !== PHP_SESSION_ACTIVE || !array_key_exists($this->sessionTimeoutKey, $_SESSION)) {
            $expireTime = time() - $this->sessionLifetime;
        } else {
            $expireTime = $_SESSION[$this->sessionTimeoutKey];
        }

        $cookieParams = [
            sprintf(
                'expires=%s; max-age=%s',
                gmdate('D, d M Y H:i:s T', $expireTime),
                $this->sessionLifetime
            ),
        ];

        if (trim($this->getSessionSetting('cookie_path')) !== '') {
            $cookieParams[] = 'path=' . $this->getSessionSetting('cookie_path');
        }

        if (trim($this->getSessionSetting('cookie_domain')) !== '') {
            $cookieParams[] = 'domain=' . $this->getSessionSetting('cookie_domain');
        }

        if ((bool) $this->getSessionSetting('cookie_secure')) {
            $cookieParams[] = 'secure';
        }

        if ((bool) $this->getSessionSetting('cookie_httponly')) {
            $cookieParams[] = 'httponly';
        }

        return $response->withAddedHeader(
            'Set-Cookie',
            sprintf(
                '%s=%s; %s',
                urlencode($this->sessionName),
                urlencode(session_id()),
                implode('; ', $cookieParams)
            )
        );
    }

    /**
     * Generates cryptographically secure session identifier.
     *
     * @param int $length
     *
     * @return string
     */
    final public static function generateSessionId($length = 80)
    {
        return substr(
            preg_replace('/[^a-zA-Z0-9-]+/', '', base64_encode(random_bytes((int) $length))),
            0,
            (int) $length
        );
    }

    /**
     * Retrieve session ini setting.
     *
     * @param string     $setting
     * @param null|mixed $default
     *
     * @return null|string
     */
    private function getSessionSetting($setting, $default = null)
    {
        $param = ini_get($this->normalizeSessionSettingName($setting));

        if (is_numeric($param)) {
            return (int) $param;
        }

        $param = trim($param);

        return $param !== '' ? $param : $default;
    }

    /**
     * Set session ini setting.
     *
     * @param string $setting
     * @param mixed  $value
     */
    private function setSessionSetting($setting, $value)
    {
        ini_set($this->normalizeSessionSettingName($setting), $value);
    }

    /**
     * Normalize session setting name to start with 'session.'.
     *
     * @param string $setting
     *
     * @return string
     */
    private function normalizeSessionSettingName($setting)
    {
        return strpos($setting, 'session.') !== 0 ? 'session.' . $setting : $setting;
    }
}
