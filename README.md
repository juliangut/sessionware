[![PHP version](https://img.shields.io/badge/PHP-%3E%3D7.0-8892BF.svg?style=flat-square)](http://php.net)
[![Latest Version](https://img.shields.io/packagist/vpre/juliangut/sessionware.svg?style=flat-square)](https://packagist.org/packages/juliangut/sessionware)
[![License](https://img.shields.io/github/license/juliangut/sessionware.svg?style=flat-square)](https://github.com//sessionware/blob/master/LICENSE)

[![Build Status](https://img.shields.io/travis/juliangut/sessionware.svg?style=flat-square)](https://travis-ci.org/juliangut/sessionware)
[![Style Check](https://styleci.io/repos/56336022/shield)](https://styleci.io/repos/56336022)
[![Code Quality](https://img.shields.io/scrutinizer/g/juliangut/sessionware.svg?style=flat-square)](https://scrutinizer-ci.com/g/juliangut/sessionware)
[![Code Coverage](https://img.shields.io/coveralls/juliangut/sessionware.svg?style=flat-square)](https://coveralls.io/github/juliangut/sessionware)

[![Total Downloads](https://img.shields.io/packagist/dt/juliangut/sessionware.svg?style=flat-square)](https://packagist.org/packages/juliangut/sessionware/stats)
[![Monthly Downloads](https://img.shields.io/packagist/dm/juliangut/sessionware.svg?style=flat-square)](https://packagist.org/packages/juliangut/sessionware/stats)

# PSR7 compatible session management

Encapsulates PHP session management into a nice API compatible with PSR7.

Generates a 80 character long session_id using `random_bytes`, a truly cryptographically secure pseudo-random generator, instead of `session.hash_function` hash algorithm.

## Installation

### Composer

```
composer require juliangut/sessionware
```

## Usage

```php
require 'vendor/autoload.php';
```

Stand alone session management.

```php
use Jgut\Sessionware\Configuration;
use Jgut\Sessionware\Handler\Native as NativeHandler;
use Jgut\Sessionware\Manager\Native as NativeManager;
use Jgut\Sessionware\Session;

$sessionSettings = [
  'name' => 'myProjectSessionName',
  'lifetime' => Configuration::LIFETIME_EXTENDED, // 1 hour
];
$configuration = new Configuration($sessionSettings);
$handler = new NativeHandler();
$manager = new NativeManager($configuration, $sessionHandler);

$manger->setSessionId('Get session id from cookie');

$session = new Session($manager);

$session->start();

$session->set('sessionKey', 'value');

$session->close();
```

Integrated on a Middleware workflow:

```php
require 'vendor/autoload.php';

use Jgut\Sessionware\Configuration;
use Jgut\Sessionware\Handler\Native as NativeHandler;
use Jgut\Sessionware\Manager\Native as NativeManager;
use Jgut\Sessionware\Middleware\SessionHandling;
use Jgut\Sessionware\Middleware\SessionStart;

$sessionSettings = [
  'name' => 'myProjectSessionName',
  'lifetime' => Configuration::LIFETIME_EXTENDED, // 1 hour
];
$configuration = new Configuration($sessionSettings);
$handler = new NativeHandler();
$manager = new NativeManager($configuration, $sessionHandler);

$app = new \YourMiddlewareAwareApplication();
$app->addMiddleware(new SessionHandling($manager));
$app->addMiddleware(new SessionStart());

// Routes

$app->run();
```

### Configuration

```php
$configuration = new Configuration([
  'name' => 'Sessionware',
  'savePath' => '/tmp/Sessionware',
  'lifetime' => SessionWare::SESSION_LIFETIME_NORMAL,
  'timeoutKey' => '__SESSIONWARE_TIMEOUT_TIMESTAMP__',
  'cookieDomain' => 'example.com',
  'cookiePath' => '/',
  'cookieSecure' => false,
  'cookieHttpOnly' => true,
]);
```

#### name

Assigns session name, default PHP `PHPSESSID` session name will be used if none provided. **It is highly recommended to set a custom session name**

> If using PHP's built-in 'files' session save handler you **MUST** provide a custom session name.

#### savePath

Native handler specific configuration, used if default 'files' session save handler is specified in `session.save_handler`. It defaults to `session.save_path` ini setting or `sys_get_temp_dir()` if empty.

Final session save path will be determined by joining this parameter with session name (other than default `PHPSESSID`). This is done so that session files for current script gets separated, from other script's, into its own directory.

Session garbage collector uses file access time to determine and remove expired session files. If files from sessions with different lifetime are located in the same directory they could be removed by other script/application as there is no way for the garbage collector to tell which script/application they belong to.

#### lifetime

Number of seconds for the session to be considered valid. uses `session.gc_maxlifetime` and `session.cookie_lifetime` to discover PHP configured session lifetime if none provided. Finally it defaults to `Session::SESSION_LIFETIME_DEFAULT` (24 minutes) if previous values are not available or their value is zero.

There are six session lifetime constants available for convenience:

* `Session::SESSION_LIFETIME_FLASH` = 5 minutes
* `Session::SESSION_LIFETIME_SHORT` = 10 minutes
* `Session::SESSION_LIFETIME_NORMAL` = 15 minutes
* `Session::SESSION_LIFETIME_DEFAULT` = 24 minutes
* `Session::SESSION_LIFETIME_EXTENDED` = 1 hour
* `Session::SESSION_LIFETIME_INFINITE` = `PHP_INT_MAX`, around 1145 years on x86_64 architecture

#### timeoutKey

Parameter stored in session array to control session validity according to `lifetime` parameter. Defaults to 
```
\Jgut\Sessionware::SESSION_TIMEOUT_KEY_DEFAULT = '__SESSIONWARE_TIMEOUT_TIMESTAMP__';
```

_It is advised not to change this value unless it conflicts with one of your own session keys (which is unlikely if not directly impossible)_

#### cookiePath, cookieDomain, cookieSecure and cookieHttpOnly

Configure session cookie parameters. Defaults to PHP session cookie `session.cookie_path`, `session.cookie_domain`, `session.cookie_secure` and `session.cookie_httponly` respectively  if not provided.

### Manager

The responsible of actual session management.

Currently only `Native` manager exist that uses built-in PHP session capabilities.

#### Requisites

Some session ini settings need to be set to specific values prior to start session, otherwise session will fail to start:

* `session.use_trans_sid` to `false`
* `session.use_cookies` to `true`
* `session.use_only_cookies` to `true`
* `session.use_strict_mode` to `false`
* `session.cache_limiter` to `''` _(empty string)_

> Prevents session headers to be automatically sent to user by PHP itself. **It's the developer's responsibility to include corresponding cache headers in response object**, which should be the case in the first place instead of relying on PHP environment settings.

### Handler

Handlers accompany Native manager and is a replacement for `session.save_handler` ini setting. It allows for the selection of several ways of persisting session:

* `\Jgut\Sessionware\Handler\Native` use PHP built-in `files` session saving
* `\Jgut\Sessionware\Handler\Memory` an in-memory session for testing purposes
* `\Jgut\Sessionware\Handler\Memcached` use a memcached service to save session
* `\Jgut\Sessionware\Handler\Redis` use a Redis instance to store session

### Session

The session manager providing a nice OOP API to access session related actions:

* `Session::start()` session starting
* `Session::isActive()` verification of active session
* `Session::getId()` session id retrieval
* `Session::regenerateId()` cryptographically secure session id regeneration
* `Session::has($var)` verifying a variable is saved in session
* `Session::set($var, $val)` saving a variable into session
* `Session::get($var)` getting a variable from session
* `Session::remove($var)` removing a variable from session
* `Session::clear()` emptying session variables
* `Session::close()` closing session saving its contents
* `Session::destroy()` destroying session and all its contents

**Never** make use of PHP built-in session handling "session_*" function (Session object won't be in sync) or `$_SESSION` global variable (changes will be ignored and overridden).

#### Events

Session raise events, to which you can hook a callback to, during executing lifecycle:

* `pre.session_start` triggered right before session is started
* `post.session_start` triggered right after session has been started
* `pre.session_regenerate_id` triggered right before session id is regenerated
* `post.session_regenerate_id` triggered after before session id has been regenerated 
* `pre.session_close` triggered right before session is closed
* `post.session_close` triggered right after session has been closed
* `pre.session_destroy` triggered right before session is destroyed
* `post.session_destroy` triggered right after session has been destroyed
* `pre.session_timeout` triggered right before session is wiped when session timeout is reached
* `post.session_timeout` triggered right after session has been restarted due to session timeout

Events provide current Session object as parameter:

```php
use Jgut\Sessionware\Session

$session = new Session($manager);
$session->addListener('pre.session_close', function(Session $session) {
    echo sprintf('session "%s" is being closed', $session->getId());
})
$session->addListener('post.session_close', function(Session $session) {
    echo sprintf('new session "%s" created', $session->getId());
})
```

## Contributing

Found a bug or have a feature request? [Please open a new issue](https://github.com/juliangut/sessionware/issues). Have a look at existing issues before.

See file [CONTRIBUTING.md](https://github.com/juliangut/sessionware/blob/master/CONTRIBUTING.md)

## License

See file [LICENSE](https://github.com/juliangut/sessionware/blob/master/LICENSE) included with the source code for a copy of the license terms.
