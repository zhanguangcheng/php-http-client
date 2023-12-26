HTTP client library for PHP
============================

README.md | [中文 README.md](README_zh.md)

A simple and powerful HTTP client library for PHP.

## Features

* Send GET, POST, PATCH, PUT, DELETE, etc. requests
* File upload and download
* Request retry
* cURL handle reuse, improve performance
* Concurrent requests
* Cookie keep
* gzip support
* User authentication

## File Structure

```
/
├─src
│   HttpClient.php      HTTP client
│   Request.php         Request
│   Response.php        Response
│   Options.php         Request options
│   CookieJar.php       Cookie keep
├─tests                 Test cases
│   ...
```

## Installation

Requirements

- PHP >= 8.0
- curl extension
- json extension

```bash
composer require zhanguangcheng/php-http-client
```

## Basic Usage

```php
use Zane\HttpClient\HttpClient;

$client = HttpClient::create();

$response = $client->get('https://httpbin.org/get');
$statusCode = $response->getStatusCode();
// $statusCode = 200
$contentType = $response->getHeaderLine('content-type');
// $contentType = 'application/json'
$content = $response->getContent();
// $content = '{"args":{}, "headers":{"Accept": "*/*", ...}}'
$content = $response->toArray();
// $content = ['args' => [], 'headers' => ['Accept' => '*/*', ...]]
```

## Configuration

HttpClient contains many options that control how requests are executed, including retry, concurrency, proxy, authentication, cookie keep, etc. These options can be defined globally (apply to all requests) and per request (override any
global options).

You can create a client with options using `HttpClient::create($options)`, `$options` is global options.

```php
use Zane\HttpClient\HttpClient;
use Zane\HttpClient\Options;

$client = HttpClient::create([
    Options::BASE_URL => 'https://httpbin.org',
    Options::HEADERS => ['header-name' => 'header-value'],
    Options::MAX_REDIRECTS => 7,
    Options::MAX_RETRY => 3,
    Options::TIMEOUT => 3,
]);
```

or, combined with the getter and setter of the [Options](https://github.com/zhanguangcheng/php-http-client/blob/master/src/Options.php) class:

```php
use Zane\HttpClient\HttpClient;
use Zane\HttpClient\Options;

$client = HttpClient::create(
    (new Options())
        ->setBaseUrl('https://...')
        ->setHeaders(['header-name' => 'header-value'])
        ->toArray()
);
```

Send a request with options:

```php
use Zane\HttpClient\HttpClient;
use Zane\HttpClient\Options;

$client = HttpClient::create();

$client->get('https://httpbin.org/get', ['query-foo' => 'query-bar'], [
    Options::HEADERS => ['header-name' => 'header-value'],
    Options::MAX_REDIRECTS => 7,
    Options::MAX_RETRY => 3,
    Options::TIMEOUT => 3,
]);
```

## Making Requests

### Sending Different Methods

```php
use Zane\HttpClient\HttpClient;

$client = HttpClient::create();

$client->get('https://httpbin.org', ['query' => 'value']);
$client->post('https://httpbin.org', ['body' => 'value']);
$client->put('https://httpbin.org', ['body' => 'value']);
$client->patch('https://httpbin.org', ['body' => 'value']);
$client->delete('https://httpbin.org', ['body' => 'value']);
$client->request('GET', 'https://httpbin.org');
```

### Query String Parameters

You can manually add them to the URL that will be added to the request, or you can define them as an associative array with the `Options::QUERY` option, which will be merged with the URL:

```php
use Zane\HttpClient\Options;

// it makes an HTTP GET request to https://httpbin.org/get?token=...&name=...
$response = $client->get('https://httpbin.org/get', [
    // these values are automatically encoded before including them in the URL
    'token' => '...',
    'name' => '...',
]);

$response = $client->post('https://httpbin.org/post', [], [
    Options::QUERY => [
        'token' => '...',
        'name' => '...',
    ]
]);
```

### Sending Request Headers

You can define the request headers to be sent with the `Options::HEADERS` option:

```php
use Zane\HttpClient\Options;

$response = $client->get('https://httpbin.org/headers', [
    Options::HEADERS => [
        'Accept' => 'application/json',
        'X-Foo' => 'Bar',
    ],
]);
```

### Sending Request Body

Use the second parameter of the `post()`, `put()`, `patch()`, `delete()` methods to send the request body:

```php
// defining data using a regular string
$response = $client->post('https://httpbin.org/post', 'raw data');

// defining data using an array of parameters
$response = $client->post('https://httpbin.org/post', ['parameter1' => 'value1', '...']);

// using a resource to get the data from it
$response = $client->post('https://httpbin.org/post', fopen('/path/to/file', 'r'));

// using a CURLFile object to upload a file
$response = $client->post('https://httpbin.org/post', [
    'file' => new \CURLFile('/path/to/file'),
]);
```

You can also use the `Options::BODY` option to define the request body to be sent:

```php
use Zane\HttpClient\Options;

$response = $client->request('POST', 'https://httpbin.org/post', [
    Options::BODY => 'raw data',
]);
```

When uploading data using the POST method, if you do not explicitly define the Content-Type request header, the `Content-Type:application/x-www-form-urlencoded` request header will be added by default.
If you want to customize the request type, you can use the `Options::CONTENT_TYPE` option, for example using the JSON format:

```php
use Zane\HttpClient\Options;

$response = $client->post('https://httpbin.org/post', ['parameter1' => 'value1', '...'], [
    Options::CONTENT_TYPE => Options::TYPE_JSON,
]);
```

### Gzip Compression

If the zlib extension is installed, the request header `Accept-Encoding: gzip` will be sent by default.
When getting the response content, if the server supports gzip compression and the response header contains `Content-Encoding: gzip`, the response content will be automatically decompressed.

```php
$response = $client->get('https://httpbin.org/gzip');
$content = $response->getContent();
// $content = '{"args":{}, "headers":{"Accept": "*/*", ...}}'
```

You can also turn off gzip compression:

```php
use Zane\HttpClient\Options;

$response = $client->get('https://httpbin.org/gzip', [
    Options::ACCEPT_GZIP => false,
]);
```

### Redirects

By default, the HTTP client will track redirects when making requests, and track 5 redirects by default. Use the `Options::MAX_REDIRECTS` setting to configure this behavior:

```php
use Zane\HttpClient\Options;

$response = $client->get('https://httpbin.org/redirect/3', [], [
    Options::MAX_REDIRECTS => 0,
]);
```

### Retry Failed Requests

Sometimes, requests fail due to network issues or temporary server errors. You can use the `Options::MAX_RETRY` option to automatically retry failed requests.
By default, failed requests are retried up to 3 times, with a delay between retries of 1 second for the first retry; 3 seconds for the second retry; and 5 seconds for the third retry. The conditions for retrying are: request timeout or
response status code is one of 423, 425, 429, 500, 502, 503, 504, 507 and 510.

```php
use Zane\HttpClient\Options;

$response = $client->get('https://httpbin.org/get', [], [
    Options::MAX_RETRY => 3,
]);
```

### User Authentication

HttpClient supports different authentication mechanisms. They can be defined globally in the configuration (apply to all requests) and per request (override any global authentication):

```php
use Zane\HttpClient\HttpClient;
use Zane\HttpClient\Options;

$client = HttpClient::create([
    // HTTP Basic authentication
    Options::AUTH_BASIC => ['username', 'password'],
    // HTTP Bearer authentication
    Options::AUTH_BEARER => 'token',
    // HTTP custom authentication
    Options::HEADERS => [
        'Authorization' => 'token',
    ],
])
```

### Request Proxy

HttpClient supports sending requests using HTTP proxies. They can be defined globally in the configuration (apply to all requests) and per request (override any global proxy):

```php

use Zane\HttpClient\HttpClient;
use Zane\HttpClient\Options;

$client = HttpClient::create([
    Options::PROXY => 'https://...',
]);
```

### Cookie Keep

Use the [CookieJar](https://github.com/zhanguangcheng/php-http-client/blob/master/src/CookieJar.php) class to keep the cookies in the response and send them in subsequent requests:

```php
use Zane\HttpClient\HttpClient;
use Zane\HttpClient\Options;

$client = HttpClient::create();

$jar = new CookieJar();

$response = $client->get('https://httpbin.org/cookies/set', ['name' => 'value'], [
    Options::COOKIE_JAR => $jar,
]);

$response = $client->get('https://httpbin.org/cookies', [], [
    Options::COOKIE_JAR => $jar,
]);
var_dump($response->toArray());
// ['cookies' => ['name' => 'value']]
```

### File Download

Use the `download()` method of `HttpClient` to download files, and you can use the `Options::ON_PROGRESS` option to monitor the download progress:

```php
use Zane\HttpClient\Options;

$client->download('https://httpbin.org/image/png', '/path/to/file.png', [
    Options::ON_PROGRESS => function ($ch, $downloadTotal, $downloaded) {
        // ...
    },
]);
```

### HTTPS Certificate Verification

> Certificate download address: <https://curl.haxx.se/docs/caextract.html>

By default, the system's CA certificate is used, such as the `curl.cainfo` or `openssl.cafile` configuration in the php configuration file:

```ini
[curl]
curl.cainfo = /path/to/cacert.pem

[openssl]
openssl.cafile = /path/to/cacert.pem
```

You can also use the `Options::CAFILE` option to specify the HTTPS certificate:

```php
use Zane\HttpClient\Options;

$response = $client->get('https://httpbin.org/get', [], [
    Options::CAFILE => '/path/to/cacert.pem',
]);
```

Close certificate verification (not recommended in production environment):

```php
use Zane\HttpClient\Options;

$response = $client->get('https://httpbin.org/get', [], [
    Options::VERIFY_HOST => false,
    Options::VERIFY_PEER => false,
]);
```

### Concurrent Requests

Set the second parameter of `HttpClient::create($options, $concurrency)` to the number of concurrent requests, use the `addRequest($method, $url, $options)` method to add requests, and then use the `send()` method to send requests, the
response array is in the same order as the request array.
You can also use the `Options::MAX_RETRY` option to set the failed requests when retrying concurrent requests.

```php
use Zane\HttpClient\HttpClient;
use Zane\HttpClient\Options;

$client = HttpClient::create([], 10);

for ($i = 0; $i < 100; $i++) {
    $client->addRequest('GET', 'https://httpbin.org/get', [
        Options::QUERY => ['index' => $i],
    ]);
}
$responses = $client->send();
foreach ($responses as $response) {
    $content = $response->getContent();
    // ...
}
```

## Processing Responses

All responses returned by HttpClient are objects of type [Response](https://github.com/zhanguangcheng/php-http-client/blob/master/src/Response.php) and provide the following methods:

```php
$response = $client->request('GET', 'https://...');

// gets the HTTP status code of the response
$statusCode = $response->getStatusCode();

// gets the HTTP request error code. using function curl_errno()
$statusCode = $response->getErrorCode();

// gets the HTTP request error message. using function curl_error()
$statusCode = $response->getErrorMessage();

// gets the HTTP response headers as a string
$headers = $response->getHeaderLine('content-type');

// gets the HTTP response headers as an array of strings
$headers = $response->getHeader('content-type');

// gets the HTTP headers as string[][] with the header names lower-cased
$headers = $response->getHeaders();

// gets the response body as a string
$content = $response->getContent();

// casts the response JSON content to a PHP array
$content = $response->toArray();

// returns info coming from the transport layer, such as "request_header",
// "retry_count", "total_time", "redirect_url", etc.
$httpInfo = $response->getInfo();

// you can get individual info too
$startTime = $response->getInfo('request_header');

// gets the request options
$options = $response->getOptions();
$options->getQuery();
```

## Testing

Run the following command to run the tests:

```bash
vendor/bin/phpunit
```

100% code coverage

![code coverage](code-coverage.png)

## References

* [PHP cURL](https://www.php.net/curl)
* [Symfony HTTP Client](https://symfony.com/doc/current/http_client.html)
