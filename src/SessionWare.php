<?php
/**
 * SessionWare (https://github.com/juliangut/sessionware)
 * PSR7 session manager middleware
 *
 * @license BSD-3-Clause
 * @author Julián Gutiérrez <juliangut@gmail.com>
 */

namespace Jgut\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * PHP session handler middleware.
 */
class SessionWare
{
    const SESSION_LIFETIME_FLASH    = 300; // 5 minutes
    const SESSION_LIFETIME_SHORT    = 600; // 10 minutes
    const SESSION_LIFETIME_NORMAL   = 900; // 15 minutes
    const SESSION_LIFETIME_DEFAULT  = 1440; // 24 minutes
    const SESSION_LIFETIME_EXTENDED = 3600; // 1 hour
    const SESSION_LIFETIME_INFINITE = PHP_INT_MAX; // Around 1145 years (x86_64)

    const TIMEOUT_CONTROL_KEY = '__SESSIONWARE_TIMEOUT_TIMESTAMP__';

    /**
     * Default session settings.
     *
     * @var array
     */
    protected static $defaultSettings = [
        'name' => null,
        'savePath' => null,
        'lifetime' => self::SESSION_LIFETIME_DEFAULT,
        'timeoutKey' => self::TIMEOUT_CONTROL_KEY,
    ];

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
     * Middleware constructor.
     *
     * @param array $settings
     * @param array $initialSessionParams
     */
    public function __construct(array $settings = [], array $initialSessionParams = [])
    {
        $this->settings = array_merge(
            self::$defaultSettings,
            $this->getSessionParams(),
            $settings
        );

        $this->initialSessionParams = $initialSessionParams;
    }

    /**
     * Retrieve default session parameters.
     *
     * @return array
     */
    protected function getSessionParams()
    {
        $lifeTime = (int) ini_get('session.cookie_lifetime') === 0
            ? (int) ini_get('session.gc_maxlifetime')
            : min(ini_get('session.cookie_lifetime'), ini_get('session.gc_maxlifetime'));

        return [
            'lifetime' => $lifeTime > 0 ? $lifeTime : self::$defaultSettings['lifetime'],
            'domain' => ini_get('session.cookie_domain'),
            'path' => ini_get('session.cookie_path'),
            'secure' => ini_get('session.cookie_secure'),
            'httponly' => ini_get('session.cookie_httponly'),
        ];
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
        $this->startSession($request->getCookieParams());

        $response = $next($request, $response);

        return $this->respondWithSessionCookie($response);
    }

    /**
     * Configure session settings.
     *
     * @param array $requestCookies
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    protected function startSession(array $requestCookies = [])
    {
        if (session_status() === PHP_SESSION_DISABLED) {
            // @codeCoverageIgnoreStart
            throw new \RuntimeException('PHP sessions are disabled');
            // @codeCoverageIgnoreEnd
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            throw new \RuntimeException('Session has already been started, review "session.auto_start" ini set');
        }

        $this->configureSessionName();
        $this->configureSessionId($requestCookies);
        $this->configureSessionSavePath();
        $this->configureSessionTimeout();

        // Use better session serializer when available
        if (ini_get('session.serialize_handler') === 'php' && version_compare(PHP_VERSION, '5.5.4', '>=')) {
            // @codeCoverageIgnoreStart
            ini_set('session.serialize_handler', 'php_serialize');
            // @codeCoverageIgnoreEnd
        }

        // Prevent headers from being automatically sent to client
        ini_set('session.use_trans_sid', false);
        ini_set('session.use_cookies', false);
        ini_set('session.use_only_cookies', true);
        ini_set('session.use_strict_mode', false);
        ini_set('session.cache_limiter', '');

        session_start();

        $this->manageSessionTimeout();

        // Populate session with initial parameters
        foreach ($this->initialSessionParams as $parameter => $value) {
            if (!array_key_exists($parameter, $_SESSION)) {
                $_SESSION[$parameter] = $value;
            }
        }
    }

    /**
     * Configure session name.
     */
    protected function configureSessionName()
    {
        $this->sessionName = trim($this->settings['name']) !== '' ? trim($this->settings['name']) : session_name();

        session_name($this->sessionName);
    }

    /**
     * Configure session identifier.
     *
     * @param array $requestCookies
     */
    protected function configureSessionId(array $requestCookies = [])
    {
        $sessionId = !empty($requestCookies[$this->sessionName])
            ? $requestCookies[$this->sessionName]
            : session_id();

        if (trim($sessionId) === '') {
            $sessionId = $this->generateSessionId();
        }

        session_id($sessionId);
    }

    /**
     * Configure session save path.
     *
     * @throws \RuntimeException
     */
    protected function configureSessionSavePath()
    {
        if (ini_get('session.save_handler') !== 'files') {
            // @codeCoverageIgnoreStart
            return;
            // @codeCoverageIgnoreEnd
        }

        $savePath = sys_get_temp_dir();
        if (session_save_path() !== '') {
            $savePath = rtrim(session_save_path(), DIRECTORY_SEPARATOR);
        }

        if (trim($this->settings['savePath']) !== '') {
            $savePath = trim($this->settings['savePath']);
        } elseif ($this->sessionName !== 'PHPSESSID'
            && $this->sessionName !== array_pop(explode(DIRECTORY_SEPARATOR, $savePath))
        ) {
            $savePath .= DIRECTORY_SEPARATOR . $this->sessionName;
        }

        if (!@mkdir($savePath, 0775, true) && (!is_dir($savePath) || !is_writable($savePath))) {
            throw new \RuntimeException(
                sprintf('Failed to create session save path "%s", or directory is not writable', $savePath)
            );
        }

        session_save_path($savePath);
    }

    /**
     * Configure session timeout.
     *
     * @throws \InvalidArgumentException
     */
    protected function configureSessionTimeout()
    {
        $lifetime = (int) $this->settings['lifetime'];

        if ($lifetime < 1) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a valid session lifetime', $lifetime));
        }

        $this->sessionLifetime = $lifetime;

        // Signal garbage collector with defined timeout
        ini_set('session.gc_maxlifetime', $lifetime);
    }

    /**
     * Manage session timeout.
     *
     * @throws \InvalidArgumentException
     */
    protected function manageSessionTimeout()
    {
        $timeoutKey = $this->getSessionTimeoutControlKey();

        if (array_key_exists($timeoutKey, $_SESSION) && $_SESSION[$timeoutKey] < time()) {
            session_unset();
            session_destroy();

            // Regenerate session identifier
            $sessionId = $this->generateSessionId();
            session_id($sessionId);

            session_start();
        }

        $_SESSION[$timeoutKey] = time() + $this->sessionLifetime;
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
        $cookieParams = [
            sprintf(
                'expires=%s; max-age=%s',
                gmdate('D, d M Y H:i:s T', $_SESSION[$this->getSessionTimeoutControlKey()]),
                $this->sessionLifetime
            ),
        ];

        if (trim($this->settings['path']) !== '') {
            $cookieParams[] = 'path=' . $this->settings['path'];
        }

        if (trim($this->settings['domain']) !== '') {
            $cookieParams[] = 'domain=' . $this->settings['domain'];
        }

        if ((bool) $this->settings['secure']) {
            $cookieParams[] = 'secure';
        }

        if ((bool) $this->settings['httponly']) {
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
     * Retrieve session timeout control key.
     *
     * @throws \InvalidArgumentException
     *
     * @return string
     */
    protected function getSessionTimeoutControlKey()
    {
        $timeoutKey = trim($this->settings['timeoutKey']);
        if ($timeoutKey === '') {
            throw new \InvalidArgumentException(
                sprintf('"%s" is not a valid session timeout control key name', $this->settings['timeoutKey'])
            );
        }

        return $timeoutKey;
    }

    /**
     * Generates cryptographically secure session identifier.
     *
     * @param int $length
     *
     * @return string
     */
    final protected function generateSessionId($length = 80)
    {
        return substr(
            preg_replace('/[^a-zA-Z0-9-]+/', '', base64_encode(random_bytes((int) $length))),
            0,
            (int) $length
        );
    }
}
