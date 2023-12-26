HTTP client library for PHP
============================

[README.md](README.md) | 中文 README.md

一个使用PHP开发的简单且强大的HTTP客户端库。

## 功能

* 发送GET、POST、PATCH、PUT、DELETE等请求
* 文件上传、下载
* 请求重试
* cURL句柄复用，提高性能
* 并发请求
* Cookie保持
* gzip支持
* 用户认证

## 文件结构

```
/
├─src
│   HttpClient.php      HTTP客户端
│   Request.php         请求
│   Response.php        响应
│   Options.php         请求配置项
│   CookieJar.php       Cookie保持
├─tests                 测试用例
│   ...
```

## 安装

要求

- PHP >= 8.0
- curl扩展
- JSON扩展

```bash
composer require zhanguangcheng/php-http-client
```

## 基本使用

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

## 配置

HttpClient包含很多配置项，可以控制请求执行方式，包含重试、并发、代理、认证、Cookie保持等。可以在配置中全局定义（将其应用于所有请求）和每个请求（覆盖任何全局配置）。

可以使用`HttpClient::create($options)`创建一个客户端，`$options`为全局选项配置。

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

或者，使用[Options](https://github.com/zhanguangcheng/php-http-client/blob/master/src/Options.php)类的getter和setter结合起来：

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

使用局部配置发送请求：

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

## 创建请求

### 发送不同方法的请求

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

### 查询字符串参数

您可以手动将添加到请求的URL，也可以通过`Options::QUERY`选项将它们定义为关联数组，该数组将与URL合并：

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

### 发送请求头

使用`Options::HEADERS`选项可以定义发送的请求头：

```php
use Zane\HttpClient\Options;

$response = $client->get('https://httpbin.org/headers', [
    Options::HEADERS => [
        'Accept' => 'application/json',
        'X-Foo' => 'Bar',
    ],
]);
```

### 发送请求体

使用`post()`、`put()`、`patch()`、`delete()`方法的第二个参数可以发送请求体：

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

也可以使用`Options::BODY`选项可以定义发送的请求体：

```php
use Zane\HttpClient\Options;

$response = $client->request('POST', 'https://httpbin.org/post', [
    Options::BODY => 'raw data',
]);
```

使用POST方法上传数据时，如果您没有明确定义Content-Type的请求头，则默认会添加`Content-Type:application/x-www-form-urlencoded`请求头。
如果要自定义请求类型，可以使用`Options::CONTENT_TYPE`选项，例如使用JSON格式：

```php
use Zane\HttpClient\Options;

$response = $client->post('https://httpbin.org/post', ['parameter1' => 'value1', '...'], [
    Options::CONTENT_TYPE => Options::TYPE_JSON,
]);
```

### Gzip压缩

如果安装了zlib扩展则默认会发送请求头：`Accept-Encoding: gzip`。
获取响应内容时，如果服务器支持gzip压缩，响应头中包含`Content-Encoding: gzip`，则会自动解压缩响应内容。

```php
$response = $client->get('https://httpbin.org/gzip');
$content = $response->getContent();
// $content = '{"args":{}, "headers":{"Accept": "*/*", ...}}'
```

也可以关闭gzip压缩：

```php
use Zane\HttpClient\Options;

$response = $client->get('https://httpbin.org/gzip', [
    Options::ACCEPT_GZIP => false,
]);
```

### 重定向

默认情况下，HTTP客户端在发出请求时会跟踪重定向，默认跟踪5个重定向。使用`Options::MAX_REDIRECTS`设置来配置此行为：

```php
use Zane\HttpClient\Options;

$response = $client->get('https://httpbin.org/redirect/3', [], [
    Options::MAX_REDIRECTS => 0,
]);
```

### 重试失败的请求

某些时候，请求会因为网络问题或临时服务器错误而失败。可以使用`Options::MAX_RETRY`选项设置自动重试失败的请求。
默认情况下，失败的请求最多重试3次，重试之间的延迟第一次重试=1秒；第二次重试=3秒；第三次重试=5秒，重试的条件为：请求超时或者响应状态码为423, 425, 429, 500, 502, 503, 504, 507和510其中之一。

```php
use Zane\HttpClient\Options;

$response = $client->get('https://httpbin.org/get', [], [
    Options::MAX_RETRY => 3,
]);
```

### 用户认证

HttpClient支持不同的身份验证机制。它们可以在配置中全局定义（将其应用于所有请求）和每个请求（覆盖任何全局身份验证）：

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

### 请求代理

HttpClient支持使用HTTP代理发送请求。它们可以在配置中全局定义（将其应用于所有请求）和每个请求（覆盖任何全局代理）：

```php

use Zane\HttpClient\HttpClient;
use Zane\HttpClient\Options;

$client = HttpClient::create([
    Options::PROXY => 'https://...',
]);
```

### Cookie保持

使用[CookieJar](https://github.com/zhanguangcheng/php-http-client/blob/master/src/CookieJar.php)类来保存响应中的Cookie，然后在后续请求中发送它们：

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

### 文件下载

使用`HttpClient的download()`方法下载文件，可以使用`Options::ON_PROGRESS`选项来监控下载进度：

```php
use Zane\HttpClient\Options;

$client->download('https://httpbin.org/image/png', '/path/to/file.png', [
    Options::ON_PROGRESS => function ($ch, $downloadTotal, $downloaded) {
        // ...
    },
]);
```

### HTTPS证书

> 证书下载地址：<https://curl.haxx.se/docs/caextract.html>

默认使用系统的CA证书，如在php的配置文件中的`curl.cainfo` 或者 `openssl.cafile`配置：

```ini
[curl]
curl.cainfo = /path/to/cacert.pem

[openssl]
openssl.cafile = /path/to/cacert.pem
```

也可以使用`Options::CAFILE`选项来指定HTTPS证书：

```php
use Zane\HttpClient\Options;

$response = $client->get('https://httpbin.org/get', [], [
    Options::CAFILE => '/path/to/cacert.pem',
]);
```

关闭证书验证（正式环境中不推荐）：

```php
use Zane\HttpClient\Options;

$response = $client->get('https://httpbin.org/get', [], [
    Options::VERIFY_HOST => false,
    Options::VERIFY_PEER => false,
]);
```

### 并发请求

设置`HttpClient::create($options, $concurrency)`中的第二个参数为并发请求数量，使用`addRequest($method, $url, $options)`方法添加请求，然后使用`send()`方法发送请求，返回的响应数组与请求数组的顺序一致。
同样也可以使用`Options::MAX_RETRY`选项来设置重试并发请求时失败的请求。

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

## 处理响应

所有HttpClient返回的响应是[Response](https://github.com/zhanguangcheng/php-http-client/blob/master/src/Response.php)类型的对象，该对象提供以下方法：

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

## 测试

执行以下命令运行测试：

```bash
vendor/bin/phpunit
```

代码测试覆盖率达到100%
![code coverage](code-coverage.png)

## 参考

* [PHP cURL](https://www.php.net/curl)
* [Symfony HTTP Client](https://symfony.com/doc/current/http_client.html)
