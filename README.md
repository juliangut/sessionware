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

Encapsulates session management into a nice PSR7 compatible API.

Automatically Regenerates session identifier to a 80 character long identifier using `random_bytes`, a truly cryptographically secure pseudo-random generator, instead of `session.hash_function` algorithm.

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
$manager = new NativeManager(
    new Configuration($sessionSettings), 
    new NativeHandler()
);
$session = new Session($manager);

$session->setId('Get session id from cookie');

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
use Jgut\Sessionware\Session;

$sessionSettings = [
  'name' => 'myProjectSessionName',
  'lifetime' => Configuration::LIFETIME_EXTENDED, // 1 hour
];
$manager = new NativeManager(
    new Configuration($sessionSettings), 
    new NativeHandler()
);
$session = new Session($manager);

$app = new \YourMiddlewareAwareApplication();
$app->addMiddleware(new SessionStart());
$app->addMiddleware(new SessionHandling($session));

// Routes

$app->run();
```

### Configuration

```php
use Defuse\Crypto\Key;

$configuration = new Configuration([
  'name' => 'Sessionware',
  'savePath' => '/tmp/Sessionware',
  'lifetime' => SessionWare::SESSION_LIFETIME_NORMAL,
  'cookieDomain' => 'example.com',
  'cookiePath' => '/',
  'cookieSecure' => false,
  'cookieHttpOnly' => true,
  'encryptionKey' => Key::loadFromAsciiSafeString('...'),
  'timeoutKey' => '__SESSIONWARE_TIMEOUT_TIMESTAMP__',
]);
```

#### name

Assigns session name, default PHP `PHPSESSID` session name will be used if none provided. **It is highly recommended to set a custom session name in every case**

> If using PHP's built-in 'files' session save handler you **MUST** provide a custom session name.

#### savePath

Native handler specific configuration, used if default 'files' session save handler is specified in `session.save_handler` ini setting. It defaults to `session.save_path` ini setting or `sys_get_temp_dir()` if empty.

Resulting "session save path" will be determined by joining this parameter with session name (other than default `PHPSESSID`). This is done so that session files for current script gets separated from other script's into its own directory.

'files' handler garbage collector uses file access time to determine and remove expired session files. If files from sessions with different lifetime are located in the same directory they could be removed by other script/application as there is no way for the garbage collector to tell which script/application they belong to.

#### lifetime

Number of seconds for the session to be considered valid. uses `session.gc_maxlifetime` and `session.cookie_lifetime` to discover PHP configured session lifetime if none provided. Finally it defaults to `Session::SESSION_LIFETIME_DEFAULT` (24 minutes) if previous values are not available or their value is zero.

There are six session lifetime constants available for convenience:

* `Session::SESSION_LIFETIME_FLASH` = 5 minutes
* `Session::SESSION_LIFETIME_SHORT` = 10 minutes
* `Session::SESSION_LIFETIME_NORMAL` = 15 minutes
* `Session::SESSION_LIFETIME_DEFAULT` = 24 minutes
* `Session::SESSION_LIFETIME_EXTENDED` = 1 hour
* `Session::SESSION_LIFETIME_INFINITE` = `PHP_INT_MAX`, around 1145 years on x86_64 architecture

#### cookiePath, cookieDomain, cookieSecure and cookieHttpOnly

Configure session cookie parameters. Defaults to PHP session cookie `session.cookie_path`, `session.cookie_domain`, `session.cookie_secure` and `session.cookie_httponly` respectively  if not provided.

#### encryptionKey

Serialized session content can be encrypted before being persisted and decrypted on session start. In order to use the encryption/decryption of session data Defuse's [php-encryption](https://github.com/defuse/php-encryption) library is needed.

```php
composer require defuse/php-encryption
```

#### timeoutKey

Parameter stored in session array to control session validity according to `lifetime` parameter. Defaults to `
\Jgut\Sessionware\Configuration::TIMEOUT_KEY_DEFAULT`

_It is advised not to change this value unless it conflicts with one of your own session keys, which is unlikely if not directly impossible_

### Handler

Handlers are only used with Native manager and are a replacement for `session.save_handler` ini setting. It allows for the selection of several ways of persisting session data:

* `\Jgut\Sessionware\Handler\File` use a blocking filesystem to store session
* `\Jgut\Sessionware\Handler\Memcached` use a Memcached service to save session
* `\Jgut\Sessionware\Handler\Memory` an in-memory session for testing purposes
* `\Jgut\Sessionware\Handler\Native` use PHP built-in `files` session saving
* `\Jgut\Sessionware\Handler\Predis` use a Predis Client instance to store session
* `\Jgut\Sessionware\Handler\Redis` use a Redis instance to store session

```php
$memcached = new \Memcached();
$memcached->addServer('127.0.0.1', 11211);

$handler = new \Jgut\Sessionware\Handler\Memcached($memcached));
```

### Manager

The responsible of actual session management. Currently only `Native` manager exist that wraps built-in PHP session management capabilities.

#### Native manager

Be aware that right before session start `session.serialize_handler` ini setting will be set to 'php_serialize' and `session.gc_maxlifetime` will be updated to session lifetime as defined in configuration

```php
$configuration = new \Jgut\Sessionware\Configuration($settings);
$handler = new \Jgut\Sessionware\Handler\Memory();

$manager = new \Jgut\Sessionware\Manager\Native($configuration, $handler));
```

##### Requisites

In order to use Native manager, due to how PHP handles headers when sessions are started, some ini settings need to be set to specific values prior to session start, otherwise it will fail to start:

* `session.use_trans_sid` to `false`
* `session.use_cookies` to `true`
* `session.use_only_cookies` to `true`
* `session.use_strict_mode` to `false`
* `session.cache_limiter` to `''` _(empty string)_

There is a helper method to configure this ini settings for you: `\Jgut\Sessionware\Manager\Native::configureIniSettings`

> This values prevent session headers to be automatically sent to user by PHP itself. **It's the developer's responsibility to include corresponding cache headers in response object**, which should be the case in the first place instead of relying on PHP environment.

### Session

> Be aware that only scalar values are allowed as session variables. Object serialization is cumbersome and thus it's left to the user

The session manager provides a nice OOP API to access session related actions:

* `Session::setId()` assign session identifier BEFORE session is started
* `Session::getId()` session identifier retrieval
* `Session::start()` session start
* `Session::isActive()` verification of active session
* `Session::regenerateId()` cryptographically secure session identifier regeneration
* `Session::has($var)` verify a variable is saved in session
* `Session::set($var, $val)` save a variable into session
* `Session::get($var)` get a variable from session
* `Session::remove($var)` remove a variable from session
* `Session::clear()` remove all session variables
* `Session::reset()` Revert session to its original data
* `Session::abort()` Close session keeping original data
* `Session::close()` close session saving its contents
* `Session::destroy()` destroy session and all its contents

```php
$session = new \Jgut\Sessionware\Session($manager, ['user' => null]);
```

#### Important note!

**Never** make use of PHP built-in "session_*" functions (Session object would end up not being in sync) or `$_SESSION` global variable (changes will be ignored and overridden). **Use Session object API instead**

#### Events

Session raises events to which you can hook a callback to, during executing lifecycle:

* `pre.start` triggered right before session is started
* `post.start` triggered right after session has been started
* `pre.regenerate_id` triggered right before session id is regenerated
* `post.regenerate_id` triggered after before session id has been regenerated
* `pre.reset` triggered right before session is reset
* `post.reset` triggered right after session has been reset
* `pre.abort` triggered right before session is aborted
* `post.abort` triggered right after session has been aborted
* `pre.close` triggered right before session is closed
* `post.close` triggered right after session has been closed
* `pre.destroy` triggered right before session is destroyed
* `post.destroy` triggered right after session has been destroyed
* `pre.timeout` triggered right before session is wiped when session timeout is reached
* `post.timeout` triggered right after session has been restarted due to session timeout

Events provide current Session object as parameter:

```php
use Jgut\Sessionware\Session

$session = new Session($manager);
$session->addListener('pre.close', function($event, Session $session) {
    echo sprintf('Session "%s" is being closed', $session->getId());
})
$session->addListener('post.close', function($event, Session $session) {
    echo sprintf('Session "%s" has been closed', $session->getId());
})
```

## Migration from 1.x

* Settings have been moved into Configuration object. This object accepts an array of settings on instantiation so it's just a matter of providing the settings to it
* Review configuration settings names, some are slightly changed
* Middleware use is now separated from core session management. SessionHandling middleware needs an instance of Session
* All session related actions have been moved to Session object instead of relying in built'in PHP session management, which you should not use

## Contributing

Found a bug or have a feature request? [Please open a new issue](https://github.com/juliangut/sessionware/issues). Have a look at existing issues before.

See file [CONTRIBUTING.md](https://github.com/juliangut/sessionware/blob/master/CONTRIBUTING.md)

## License

See file [LICENSE](https://github.com/juliangut/sessionware/blob/master/LICENSE) included with the source code for a copy of the license terms.
