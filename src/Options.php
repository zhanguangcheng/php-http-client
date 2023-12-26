<?php

/**
 * This file is part of the HttpClient package.
 *
 * @author  zhanguangcheng<14712905@qq.com>
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 * @noinspection PhpUnused
 */

namespace Zane\HttpClient;

use InvalidArgumentException;

use CURLFile;
use JsonException;
use function array_key_exists;
use function array_merge;
use function count;
use function http_build_query;
use function in_array;
use function is_array;
use function is_resource;
use function is_string;
use function json_encode;
use function str_ends_with;
use function str_starts_with;
use function strpos;
use function strtoupper;
use function ucfirst;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * The request options.
 */
class Options
{
    const TYPE_URL_ENCODED = 'application/x-www-form-urlencoded';
    const TYPE_FORM_DATA = 'multipart/form-data';
    const TYPE_TEXT = 'text/plain';
    const TYPE_JSON = 'application/json';
    const TYPE_XML = 'application/xml';
    const TYPE_BINARY = 'application/octet-stream';

    public const URL = 'url';
    public const METHOD = 'method';
    public const BASE_URL = 'baseUrl';
    public const QUERY = 'query';
    public const BODY = 'body';
    public const CONTENT_TYPE = 'contentType';
    public const HEADERS = 'headers';
    public const COOKIES = 'cookies';
    public const USER_AGENT = 'userAgent';
    public const REFERER = 'referer';
    public const AUTH_BASIC = 'authBasic'; // [username, password]
    public const AUTH_BEARER = 'authBearer'; // token
    public const PROXY = 'proxy'; // ip:port
    public const ON_PROGRESS = 'onProgress';
    public const TIMEOUT = 'timeout';
    public const MAX_REDIRECTS = 'maxRedirects';
    public const MAX_RETRY = 'maxRetry';
    public const RETRY_COUNT = 'retryCount';
    public const VERIFY_PEER = 'verifyPeer';
    public const VERIFY_HOST = 'verifyHost';
    public const CAFILE = 'cafile';
    public const ACCEPT_GZIP = 'acceptGzip';
    public const COOKIE_JAR = 'cookieJar';
    public const CURL_OPTIONS = 'curlOptions';

    protected ?string $url = null;
    protected string $method = 'GET';
    protected ?string $baseUrl = null;
    protected array $query = [];
    protected mixed $body = null;
    protected array $headers = [];
    protected array $cookies = [];
    protected ?string $userAgent = null;
    protected ?string $referer = null;
    protected array $authBasic = [];
    protected ?string $authBearer = null;
    protected ?string $proxy = null;
    protected ?string $contentType = null;
    protected int $timeout = 5;
    protected mixed $onProgress = null;
    protected int $maxRedirects = 5;
    protected int $maxRetry = 3;
    protected int $retryCount = 0;
    protected bool $verifyPeer = false;
    protected bool $verifyHost = false;
    protected ?string $cafile = null;
    protected bool $acceptGzip = true;
    protected ?CookieJar $cookieJar = null;
    protected array $curlOptions = [];

    public function __construct(array $options = [])
    {
        $this->reset();
        $this->applyOptions($options);
    }

    public function applyOptions(array $options = []): self
    {
        $optionKeys = Options::getDefault();
        foreach ($options as $key => $value) {
            if (!array_key_exists($key, $optionKeys)) {
                throw new InvalidArgumentException("Unsupported option:$key");
            }
            $methodName = "set" . ucfirst($key);
            $this->$methodName($value);
        }
        return $this;
    }

    /**
     * Merge the options.
     *
     * @param array $options1 The options to be merged to.
     * @param array $options2 The options to be merged from.
     * @return array
     */
    public static function mergeOptions(array $options1, array $options2): array
    {
        foreach ($options1 as $key => $value) {
            if (in_array($key, [Options::QUERY, Options::HEADERS, Options::COOKIES])) {
                $options2[$key] = array_merge($value, $options2[$key] ?? []);
            } elseif ($key === Options::CURL_OPTIONS) {
                $options2[$key] = $value + ($options2[$key] ?? []);
            } else {
                $options2[$key] = $options2[$key] ?? $value;
            }
        }
        return $options2;
    }

    public static function getDefault(): array
    {
        return [
            Options::URL => null,
            Options::BASE_URL => null,
            Options::METHOD => 'GET',
            Options::QUERY => [],
            Options::BODY => null,
            Options::CONTENT_TYPE => null,
            Options::HEADERS => [],
            Options::COOKIES => [],
            Options::USER_AGENT => 'ZaneHttpClient/v' . HttpClient::VERSION,
            Options::REFERER => null,
            Options::AUTH_BASIC => [],
            Options::AUTH_BEARER => null,
            Options::PROXY => null,
            Options::ON_PROGRESS => null,
            Options::TIMEOUT => 5,
            Options::MAX_REDIRECTS => 5,
            Options::MAX_RETRY => 3,
            Options::RETRY_COUNT => 0,
            Options::VERIFY_PEER => true,
            Options::VERIFY_HOST => true,
            Options::CAFILE => null,
            Options::ACCEPT_GZIP => true,
            Options::COOKIE_JAR => null,
            Options::CURL_OPTIONS => [],
        ];
    }

    /**
     * Get the request options.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            Options::URL => $this->getUrl(),
            Options::METHOD => $this->getMethod(),
            Options::BASE_URL => $this->getBaseUrl(),
            Options::QUERY => $this->getQuery(),
            Options::BODY => $this->getBody(),
            Options::CONTENT_TYPE => $this->getContentType(),
            Options::HEADERS => $this->getHeaders(),
            Options::COOKIES => $this->getCookies(),
            Options::USER_AGENT => $this->getUserAgent(),
            Options::REFERER => $this->getReferer(),
            Options::AUTH_BASIC => $this->getAuthBasic(),
            Options::AUTH_BEARER => $this->getAuthBearer(),
            Options::PROXY => $this->getProxy(),
            Options::ON_PROGRESS => $this->getOnProgress(),
            Options::TIMEOUT => $this->getTimeout(),
            Options::MAX_REDIRECTS => $this->getMaxRedirects(),
            Options::MAX_RETRY => $this->getMaxRetry(),
            Options::RETRY_COUNT => $this->getRetryCount(),
            Options::VERIFY_PEER => $this->isVerifyPeer(),
            Options::VERIFY_HOST => $this->isVerifyHost(),
            Options::CAFILE => $this->getCafile(),
            Options::ACCEPT_GZIP => $this->isAcceptGzip(),
            Options::COOKIE_JAR => $this->getCookieJar(),
            Options::CURL_OPTIONS => $this->getCurlOptions(),
        ];
    }

    /**
     * Reset the options.
     *
     * @return Options
     */
    public function reset(): self
    {
        foreach (Options::getDefault() as $key => $value) {
            $this->$key = $value;
        }
        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function getFullUrl(): string
    {
        $url = $this->baseUrl && !str_starts_with($this->url, 'http') ? $this->baseUrl . $this->url : $this->url;
        if ($this->query) {
            $link = strpos($url, '?') ? '&' : '?';
            $url .= $link . http_build_query($this->query);
        }
        return $url;
    }

    public function setUrl(?string $url): self
    {
        $this->url = $url;
        return $this;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function setMethod(string $method): self
    {
        $this->method = strtoupper($method);
        return $this;
    }

    public function getBaseUrl(): ?string
    {
        return $this->baseUrl;
    }

    public function setBaseUrl(?string $baseUrl): self
    {
        $this->baseUrl = $baseUrl;
        return $this;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function setTimeout(int $timeout): self
    {
        if ($timeout < 0) {
            throw new InvalidArgumentException("timeout must be greater than 0");
        }
        $this->timeout = $timeout;
        return $this;
    }

    public function getBody(): mixed
    {
        return $this->body;
    }

    /**
     * Get the encoded request body.
     *
     * @throws JsonException
     */
    public function getEncodeBody(): mixed
    {
        if (!is_array($this->body)) {
            return $this->body;
        }
        return match ($this->contentType) {
            self::TYPE_URL_ENCODED, null => http_build_query($this->body),
            self::TYPE_JSON => json_encode($this->body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            default => $this->body,
        };
    }

    public function setBody(mixed $body): self
    {
        $this->body = $body;
        if ($this->contentType !== null) {
            return $this;
        }
        if (is_array($body)) {
            $this->setContentType(self::TYPE_URL_ENCODED);
            foreach ($body as $value) {
                if ($value instanceof CURLFile) {
                    $this->setContentType(self::TYPE_FORM_DATA);
                }
            }
        } elseif (is_resource($body)) {
            $this->setContentType(self::TYPE_BINARY);
        } elseif (is_string($body)) {
            if (str_starts_with($body, '{') && str_ends_with($body, '}')) {
                $this->setContentType(self::TYPE_JSON);
            } elseif (str_starts_with($body, '<') && str_ends_with($body, '>')) {
                $this->setContentType(self::TYPE_XML);
            } else {
                $this->setContentType(self::TYPE_TEXT);
            }
        }
        return $this;
    }

    public function getHeaders($line = false): array
    {
        if ($line) {
            $result = [];
            foreach ($this->headers as $key => $value) {
                $result[] = "$key: $value";
            }
            return $result;
        }
        return $this->headers;
    }

    public function setHeaders(array $headers): self
    {
        $this->headers = $headers;
        return $this;
    }

    public function setHeader(string $header, ?string $value): self
    {
        if ($value === null) {
            unset($this->headers[$header]);
            return $this;
        }
        $this->headers[$header] = $value;
        return $this;
    }

    public function getQuery(): array
    {
        return $this->query;
    }

    public function setQuery(array $query): self
    {
        $this->query = $query;
        return $this;
    }

    public function getMaxRedirects(): int
    {
        return $this->maxRedirects;
    }

    public function setMaxRedirects(int $maxRedirects): self
    {
        if ($maxRedirects < 0) {
            throw new InvalidArgumentException("maxRedirects must be greater than 0");
        }
        $this->maxRedirects = $maxRedirects;
        return $this;
    }

    public function getOnProgress(): mixed
    {
        return $this->onProgress;
    }

    public function setOnProgress(?callable $callback): self
    {
        $this->onProgress = $callback;
        return $this;
    }

    public function getContentType(): ?string
    {
        return $this->contentType;
    }

    public function setContentType(?string $contentType): self
    {
        $this->contentType = $contentType;
        $this->setHeader('Content-Type', $contentType);
        return $this;
    }

    public function getCurlOptions(): array
    {
        return $this->curlOptions;
    }

    public function setCurlOptions(array $curlOptions): self
    {
        $this->curlOptions = $curlOptions;
        return $this;
    }

    public function getCurlOption(int $option, $defaultValue = null): mixed
    {
        return $this->curlOptions[$option] ?? $defaultValue;
    }

    public function setCurlOption(int $option, $value): self
    {
        $this->curlOptions[$option] = $value;
        return $this;
    }

    public function getCookies(): array
    {
        return $this->cookies;
    }

    public function setCookies(array $cookies): self
    {
        $this->cookies = $cookies;
        return $this;
    }

    public function getAuthBasic(): array
    {
        return $this->authBasic;
    }

    public function setAuthBasic(array $authBasic): self
    {
        if ($authBasic && count($authBasic) !== 2) {
            throw new InvalidArgumentException("authBasic must be an array of two elements");
        }
        $this->authBasic = $authBasic;
        return $this;
    }

    public function getAuthBearer(): ?string
    {
        return $this->authBearer;
    }

    public function setAuthBearer(?string $authBearer): self
    {
        $this->authBearer = $authBearer;
        $this->setHeader('Authorization', $authBearer === null ? null : "Bearer $authBearer");
        return $this;
    }

    public function getProxy(): ?string
    {
        return $this->proxy;
    }

    public function setProxy(?string $proxy): self
    {
        $this->proxy = $proxy;
        return $this;
    }

    public function getMaxRetry(): int
    {
        return $this->maxRetry;
    }

    public function setMaxRetry(int $maxRetry): self
    {
        $this->maxRetry = max(0, $maxRetry);
        return $this;
    }

    public function getRetryCount(): int
    {
        return $this->retryCount;
    }

    public function setRetryCount(int $retryCount): self
    {
        $this->retryCount = min(50, max(0, $retryCount));
        return $this;
    }

    public function incRetryCount(): void
    {
        $this->retryCount += 1;
    }

    public function isVerifyPeer(): bool
    {
        return $this->verifyPeer;
    }

    public function setVerifyPeer(bool $verifyPeer): self
    {
        $this->verifyPeer = $verifyPeer;
        return $this;
    }

    public function isVerifyHost(): bool
    {
        return $this->verifyHost;
    }

    public function setVerifyHost(bool $verifyHost): self
    {
        $this->verifyHost = $verifyHost;
        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): self
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    public function getReferer(): ?string
    {
        return $this->referer;
    }

    public function setReferer(?string $referer): self
    {
        $this->referer = $referer;
        return $this;
    }

    public function getCafile(): ?string
    {
        return $this->cafile;
    }

    public function setCafile(?string $cafile): self
    {
        if ($cafile && !is_file($cafile)) {
            throw new InvalidArgumentException("cafile is not a file");
        }
        $this->cafile = $cafile;
        return $this;
    }

    public function isAcceptGzip(): bool
    {
        return $this->acceptGzip;
    }

    public function setAcceptGzip(bool $acceptGzip): self
    {
        $this->acceptGzip = $acceptGzip && extension_loaded('zlib');
        // Expose only one encoding, some servers mess up when more are provided
        $this->setHeader('Accept-Encoding', $acceptGzip ? 'gzip' : null);
        return $this;
    }

    public function getCookieJar(): ?CookieJar
    {
        return $this->cookieJar;
    }

    public function setCookieJar(?CookieJar $cookieJar): self
    {
        $this->cookieJar = $cookieJar;
        return $this;
    }
}
