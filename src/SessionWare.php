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

    const SESSION_TIMEOUT_KEY_DEFAULT = '__SESSIONWARE_TIMEOUT_TIMESTAMP__';

    const SESSION_ID_LENGTH = 80;

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

        // @codeCoverageIgnoreStart
        if (php_sapi_name() !== 'cli') {
            $this->verifySessionSettings();
        }
        // @codeCoverageIgnoreEnd

        $sessionSettings = array_merge($this->getSessionSettings(), $this->settings);

        $this->configureSessionName($sessionSettings);

        $this->configureSessionCookies($sessionSettings);
        $this->configureSessionSavePath($sessionSettings);
        $this->configureSessionTimeout($sessionSettings);
        $this->configureSessionSerializer();

        $this->configureSessionId($request);

        session_start();

        if (session_status() !== PHP_SESSION_ACTIVE) {
            // @codeCoverageIgnoreStart
            throw new \RuntimeException('Session could not be started');
            // @codeCoverageIgnoreEnd
        }

        if (strlen(session_id()) !== static::SESSION_ID_LENGTH) {
            $this->recreateSession();
        }

        $this->manageSessionTimeout();

        $this->populateSession($this->initialSessionParams);
    }

    /**
     * Verify session ini settings.
     *
     * @throws \RuntimeException
     *
     * @codeCoverageIgnore
     */
    final protected function verifySessionSettings()
    {
        if ((bool) $this->getSessionSetting('use_trans_sid') !== false) {
            throw new \RuntimeException('"session.use_trans_sid" ini setting must be set to false');
        }

        if ((bool) $this->getSessionSetting('use_cookies') !== true) {
            throw new \RuntimeException('"session.use_cookies" ini setting must be set to false');
        }

        if ((bool) $this->getSessionSetting('use_only_cookies') !== true) {
            throw new \RuntimeException('"session.use_only_cookies" ini setting must be set to false');
        }

        if ((bool) $this->getSessionSetting('use_strict_mode') !== false) {
            throw new \RuntimeException('"session.use_strict_mode" ini setting must be set to false');
        }

        if ($this->getSessionSetting('cache_limiter') !== null) {
            throw new \RuntimeException('"session.cache_limiter" ini setting must be set to false');
        }
    }

    /**
     * Retrieve default session settings.
     *
     * @return array
     */
    protected function getSessionSettings()
    {
        $lifeTime = (int) $this->getSessionSetting('cookie_lifetime') === 0
            ? (int) $this->getSessionSetting('gc_maxlifetime')
            : min($this->getSessionSetting('cookie_lifetime'), (int) $this->getSessionSetting('gc_maxlifetime'));

        return [
            'name'             => $this->getSessionSetting('name', 'PHPSESSID'),
            'path'             => $this->getSessionSetting('cookie_path'),
            'domain'           => $this->getSessionSetting('cookie_domain', '/'),
            'secure'           => $this->getSessionSetting('cookie_secure'),
            'httponly'         => $this->getSessionSetting('cookie_httponly'),
            'savePath'         => $this->getSessionSetting('save_path', sys_get_temp_dir()),
            'lifetime'         => $lifeTime > 0 ? $lifeTime : static::SESSION_LIFETIME_DEFAULT,
            'timeoutKey'       => static::SESSION_TIMEOUT_KEY_DEFAULT,
        ];
    }

    /**
     * Configure session name.
     *
     * @param array $settings
     *
     * @throws \InvalidArgumentException
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
                sprintf('Failed to create session save path at "%s", directory might not be write enabled', $savePath)
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
            throw new \InvalidArgumentException('Session lifetime must be at least 1');
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
     * Configure session identifier.
     *
     * @param ServerRequestInterface $request
     */
    protected function configureSessionId(ServerRequestInterface $request)
    {
        $requestCookies = $request->getCookieParams();

        if (array_key_exists($this->sessionName, $requestCookies) && trim($requestCookies[$this->sessionName]) !== '') {
            session_id(trim($requestCookies[$this->sessionName]));
        }
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

            $this->recreateSession();

            $this->emit(Event::named('post.session_timeout'), session_id());
        }

        $_SESSION[$this->sessionTimeoutKey] = time() + $this->sessionLifetime;
    }

    /**
     * Close previous session and create a new empty one.
     */
    protected function recreateSession()
    {
        $_SESSION = [];
        session_unset();
        session_destroy();

        session_id(SessionWare::generateSessionId());

        session_start();
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
    final public static function generateSessionId($length = self::SESSION_ID_LENGTH)
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
     * @param mixed|null $default
     *
     * @return mixed
     */
    private function getSessionSetting($setting, $default = null)
    {
        $setting = ini_get($this->normalizeSessionSettingName($setting));

        if (is_numeric($setting)) {
            return (int) $setting;
        }

        $setting = trim($setting);

        return $setting !== '' ? $setting : $default;
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
