# PHP Client for Amazon MWS API

[![CircleCI](https://circleci.com/gh/thiagomarini/royal-mail-client.svg?style=svg)](https://circleci.com/gh/thiagomarini/royal-mail-client)

## What for?

We got very disappointed with the official PHP client Amazon offers on their docs and decided to write a simple client based on Guzzle and suitable for PHP 7 projects.
This project is based on the official client.
The idea is to just hide all the nitty-gritty of handling requests and have as little abstraction as possible, basically you pass the request params and get a XML object back.

#### Requirements

* PHP >= 7.0

#### Usage

```php
$client = new AmazonMwsClient(
    'access key',
    'secret key',
    'seller id',
    ['marketplace id'],
    'mws auth token'
);

$optionalParams = [
    'CreatedAfter'  => AmazonMwsClient::genTime('10 September 2017'),
    'CreatedBefore' => AmazonMwsClient::genTime('20 September 2017'),
];

var_dump($client->send('ListOrders', '/Orders/2013-09-01', $optionalParams));
```

#### Hot to contribute

Pull requests are welcome :)

#### License
MIT