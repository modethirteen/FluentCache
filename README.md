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
