# FluentCache

A fluent cache builder API for [PSR-16](https://www.php-fig.org/psr/psr-16/) compatible cache components

[![github.com](https://github.com/modethirteen/FluentCache/workflows/build/badge.svg)](https://github.com/modethirteen/FluentCache/actions?query=workflow%3Abuild)
[![codecov.io](https://codecov.io/github/modethirteen/FluentCache/coverage.svg?branch=main)](https://codecov.io/github/modethirteen/FluentCache?branch=main)
[![Latest Stable Version](https://poser.pugx.org/modethirteen/fluent-cache/version.svg)](https://packagist.org/packages/modethirteen/fluent-cache)
[![Latest Unstable Version](https://poser.pugx.org/modethirteen/fluent-cache/v/unstable)](https://packagist.org/packages/modethirteen/fluent-cache)

## Requirements

* PHP 7.2, 7.3, 7.4 (main, 1.x)

## Installation

Use [Composer](https://getcomposer.org/). There are two ways to add FluentCache to your project.

From the composer CLI:

```sh
./composer.phar require modethirteen/fluent-cache
```

Or add modethirteen/fluent-cache to your project's composer.json:

```json
{
    "require": {
        "modethirteen/fluent-cache": "dev-main"
    }
}
```

`dev-main` is the main development branch. If you are using FluentCache in a production environment, it is advised that you use a stable release.

Assuming you have setup Composer's autoloader, FluentCache can be found in the `modethirteen\FluentCache\` namespace.

## Usage

The principal type that FluentCache provides is `CacheBuilder`. `CacheBuilder` is an immutable object that takes references to a cache and anonymous functions to generate a cache key, build cacheable objects, validate results, and hook an event dispatcher.

`CacheBuilder` handles the responsibility for, and obfuscates, the steps required to orchestrate the most common scenario for handling cached data:

* Check the cache for an object
  * If miss, build an object, set it in the cache, and return the object
  * If hit, return the object

Managing these steps, choosing what to profile, when to validate, or other cache-specific decision making isn't the responsibility of the calling code: the caller just wants to _get_ an object, it shouldn't care if it comes from a cache, if the data is stale, or the object is built for the first time. `CacheBuilder` separates this concern and encapsulates it, exposing custom logic hooks for reasonable flexibility.

```php
class Memcache implements \Psr\SimpleCache\CacheInterface {}

class Dispatcher implements \Psr\EventDispatcher\EventDispatcherInterface {}

$result = (new CacheBuilder())
    ->withCache(new Memcache(), function() : ?string {

        // generate a cache key - if null is returned, then the cache will be ignored
        return 'foo';
    })
    ->withCacheLifespanBuilder(function($result) : int {

        // what is the ttl for objects set in the cache?
        return 1500;
    })
    ->withCacheValidator(function($result) : bool {

        // assert that cached object is valid when fetched
        if(!($result instanceof Bar)) {
            return false;
        }
        return $result->someProperty === 'some value';
    });
    ->withBuilder(function() : object {

        // build a cacheable object if cache miss
        return new Bar();
    })
     ->withBuildValidator(function($result) : object {

        // assert that post-cache miss built object is valid
        return $result instanceof Bar;
    })

    // send cache and build stage events to trigger downstream actions such as profiling
    ->withEventDispatcher(new Dispatcher())

    // ...or set a lazy dispatcher to initialize when we attempt to fetch an object
    ->withLazyEventDispatcher(function(CacheBuilder $this) : EventDispatcherInterface {
        return new Dispatcher();
    })

    // fetch object from cache or build it and set it in the cache - the caller doesn't care!
    ->get();
```
