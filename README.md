[![PHP version](https://img.shields.io/badge/PHP-%3E%3D5.5-8892BF.svg?style=flat-square)](http://php.net)
[![Latest Version](https://img.shields.io/packagist/vpre/juliangut/sessionware.svg?style=flat-square)](https://packagist.org/packages/juliangut/sessionware)
[![License](https://img.shields.io/github/license/juliangut/sessionware.svg?style=flat-square)](https://github.com//sessionware/blob/master/LICENSE)

[![Build status](https://img.shields.io/travis/juliangut/sessionware.svg?style=flat-square)](https://travis-ci.org/juliangut/sessionware)
[![Style](https://styleci.io/repos/56336022/shield)](https://styleci.io/repos/56336022)
[![Code Quality](https://img.shields.io/scrutinizer/g/juliangut/sessionware.svg?style=flat-square)](https://scrutinizer-ci.com/g/juliangut/sessionware)
[![Code Coverage](https://img.shields.io/coveralls/juliangut/sessionware.svg?style=flat-square)](https://coveralls.io/github/juliangut/sessionware)
[![Total Downloads](https://img.shields.io/packagist/dt/juliangut/sessionware.svg?style=flat-square)](https://packagist.org/packages/juliangut/sessionware)

# SessionWare

A PSR7 session management middleware.

Automatic control of session timeout.

Generates a 80 character long session_id using `random_bytes`, a truly cryptographically secure pseudo-random generator, instead of `session.hash_function` hash algorithm.

#### Important considerations

Be aware that this middleware prevents `session_start` and PHP session mechanisms from automatically send any kind of header to the client (including session cookie and caching). Middleware appends a `Set-Cookie` header to the response object instead.

By using `session_regenerate_id()` during execution cryptographically secure session ID will be replaced by default PHP `session.hash_function` generated ID (not really secure). To prevent this from happening use `\Jgut\Middleware\Session` helper method `regenerateSessionId()` instead:

```php
\Jgut\Middleware\Session::regenerateSessionId();
```

Value of `session.cache_limiter` and `session.cache_expire` get discarded so no cache headers are sent. **It's the developer's responsibility to include corresponding cache headers in response object**, which should be the case in the first place instead of relying on PHP environment settings.

> You can use [juliangut/cacheware](https://github.com/juliangut/cacheware) which will automatically add the corresponding cache headers to response object.

## Installation

### Composer

```
composer require juliangut/sessionware
```

## Usage

```php
require 'vendor/autoload.php';

use \Jgut\Middleware\SessionWare

$configuration = [
  'name' => 'myProjectSessionName',
  'lifetime' => 1800, // 30 minutes
];

$sessionMiddleware = new SessionWare($configuration);

// Get $request and $response from PSR7 implementation
$request = new Request();
$response = new Response();

$response = $sessionMiddleware($request, $response, function() { });

// Session is started, populated with default parameters and response has session cookie header
```

Integrated on a Middleware workflow:

```php
require 'vendor/autoload.php';

use \Jgut\Middleware\SessionWare

$configuration = [
  'name' => 'myProjectSessionName',
  'lifetime' => SessionWare::SESSION_LIFETIME_NORMAL, // 15 minutes
];
$defaultSessionParams = [
  'default_timezone' => 'UTC',
]

$app = new \YourMiddlewareAwareApplication();
$app->addMiddleware(new SessionWare($configuration, $defaultParameters));
$app->run();
```

#### Session helper

There is an extra Session helper to abstract access to the $_SESSION variable. This is usefull for example when NOT accessing global variables is important for you (such as when using PHP_MD to statically analise your code)

In order to benefit from SessionWare cryptographically secure generated session id DO use

```
$session = new \Jgut\Middleware\Session;
$session->regenerate();

// Or can be called statically
\Jgut\Middleware\Session::regenerateSessionId()
```

### Config

```php
$sessionMiddleware = new SessionWare([
  'timeoutKey' => '__SESSIONWARE_TIMEOUT_TIMESTAMP__'
  'name' => 'SessionWareSession',
  'savePath' => '/tmp/SessionWareSession',
  'lifetime' => SessionWare::SESSION_LIFETIME_NORMAL,
  'domain' => 'http://example.com',
  'path' => '/',
  'secure' => false,
  'httponly' => true,
]);
```

> Default values mimic those provided by default PHP installation so the middleware can be used as a direct drop-in with automatic session timeout control 

#### timeoutKey

Parameter stored in session array to control session validity according to `lifetime` parameter. Defaults to 
```
\Jgut\Middleware\SessionWare::SESSION_TIMEOUT_KEY_DEFAULT = '__SESSIONWARE_TIMEOUT_TIMESTAMP__';
```

_It is advised not to change this value unless it conflicts with one of your own session keys (which is unlikely if not directly impossible)_

#### name

Assigns session name, default PHP `PHPSESSID` session name will be used if none provided.

> Review Important note below.

#### savePath

This configuration is used only if default 'files' session save handler is selected in `session.save_handler`.

Assigns the path to store session files. If none provided `sys_get_temp_dir()`, `session_save_path()` and session 'name' will be used to compose a unique path.

> Review Important note below.

#### lifetime

Number of seconds for the session to be considered valid. uses `session.gc_maxlifetime` and `session.cookie_lifetime` to discover PHP configured session lifetime if none provided. Finally it defaults to `SessionWare::SESSION_LIFETIME_DEFAULT` (24 minutes) if previous values are not available or their value is zero.

There are six session lifetime constants available for convenience:

* SESSION_LIFETIME_FLASH = 5 minutes
* SESSION_LIFETIME_SHORT = 10 minutes
* SESSION_LIFETIME_NORMAL = 15 minutes
* SESSION_LIFETIME_DEFAULT = 24 minutes
* SESSION_LIFETIME_EXTENDED = 1 hour
* SESSION_LIFETIME_INFINITE = `PHP_INT_MAX`, around 1145 years on x86_64 architecture

#### path, domain, secure and httponly

Shortcuts to `session.cookie_path`, `session.cookie_domain`, `session.cookie_secure` and `session.cookie_httponly`. If not provided configured cookie params will be used, so can be set using `session_set_cookie_params()` before middleware run.

## Events

You can listen to timeout events to perform actions accordingly. There are currently two events

* `pre.session_timeout` triggered right before session is wiped when session timeout is reached
* `post.session_timeout` triggered right after session has been restarted due to session timeout

Events provide sessionId as parameter:

```php
$sessionware = new SessionWare($configuration);
$sessionware->addListener('pre.session_timeout', function($sessionId) {
    echo sprintf('session "%s" timed out', $sessionId);
})
$sessionware->addListener('post.session_timeout', function($sessionId) {
    echo sprintf('new session "%s" created', $sessionId);
})
```

## Important note

### Using default 'files' session save handler
If you define a session 'lifetime' you **MUST** set a session 'savePath' or a session 'name' (different to `PHPSESSID`). This is to separate session files from other PHP scripts session files, for the garbage collector to handle expired files removal correctly.

Be aware that if this condition is not met starting a session might remove session files from other script/application as they are all located in the same directory and there is no way for the garbage collector to tell which script/application they belong to.

### Using custom session save handler

Distinguishing between different script/application session files shouldn't be a problem in this case. But be carefull not to send cookie headers (`setcookie`) directly to the client but to include them in the response object instead.

Register your custom session save handler *before* running this middleware to prevent savePath to be created.

## Contributing

Found a bug or have a feature request? [Please open a new issue](https://github.com/juliangut/sessionware/issues). Have a look at existing issues before.

See file [CONTRIBUTING.md](https://github.com/juliangut/sessionware/blob/master/CONTRIBUTING.md)

## License

See file [LICENSE](https://github.com/juliangut/sessionware/blob/master/LICENSE) included with the source code for a copy of the license terms.
