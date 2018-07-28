# PSR-7 implementation

[![Latest Version](https://img.shields.io/github/release/nyholm/psr7-server.svg?style=flat-square)](https://github.com/nyholm/psr7-server/releases)
[![Build Status](https://img.shields.io/travis/nyholm/psr7-server/master.svg?style=flat-square)](https://travis-ci.org/nyholm/psr7-server)
[![Code Coverage](https://img.shields.io/scrutinizer/coverage/g/nyholm/psr7-server.svg?style=flat-square)](https://scrutinizer-ci.com/g/nyholm/psr7-server)
[![Quality Score](https://img.shields.io/scrutinizer/g/nyholm/psr7-server.svg?style=flat-square)](https://scrutinizer-ci.com/g/nyholm/psr7-server)
[![Total Downloads](https://poser.pugx.org/nyholm/psr7-server/downloads)](https://packagist.org/packages/nyholm/psr7-server)
[![Monthly Downloads](https://poser.pugx.org/nyholm/psr7-server/d/monthly.png)](https://packagist.org/packages/nyholm/psr7-server)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)

## Installation

```bash
composer require nyholm/psr7-server
```

## Usage

```php
$psr17Factory = new Nyholm\Psr7\Factory\Psr17Factory();
$serverRequestFactory = new Nyholm\Psr7\Factory\ServerRequestFactory();

$creator = new ServerRequestCreator(
    $serverRequestFactory,
    $psr17Factory,
    $psr17Factory,
    $psr17Factory
);

$serverRequest = $creator->fromGlobals();
```
