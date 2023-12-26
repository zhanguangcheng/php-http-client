<?php

/**
 * This file is part of the HttpClient package.
 *
 * @author  zhanguangcheng<14712905@qq.com>
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace Zane\HttpClient;

use CurlHandle;
use JsonException;
use UnexpectedValueException;
use function array_merge;
use function curl_errno;
use function curl_error;
use function curl_getinfo;
use function explode;
use function extension_loaded;
use function implode;
use function is_string;
use function json_decode;
use function str_contains;
use function str_starts_with;
use function strtolower;
use function trim;
use const CURLE_OPERATION_TIMEOUTED;
use const JSON_BIGINT_AS_STRING;
use const JSON_THROW_ON_ERROR;

/**
 * Class Response
 */
class Response
{
    protected CurlHandle|false|null $ch = null;
    protected int|string $id;
    protected ?bool $isGzipd = null;
    protected string|bool|null $content;
    protected array $headers = [];
    protected array $info = [];
    protected Request $request;
    private array $headerNames = [];

    /**
     * @param Request $request request object.
     * @param string|bool|null $content response content.
     * @param array $responseHeaders response headers
     */
    public function __construct(Request $request, string|bool|null $content, array $responseHeaders = [])
    {
        $this->request = $request;
        $this->ch = $request->getHandle();
        $this->id = (int)$this->ch;
        $this->content = $content;
        foreach ($responseHeaders as $header) {
            $this->addResponseHeader($header);
        }
    }

    /**
     * @return int|string
     */
    public function getId(): int|string
    {
        return $this->id;
    }

    /**
     * Get the error code.
     *
     * @return int
     */
    public function getErrorCode(): int
    {
        return curl_errno($this->ch);
    }

    /**
     * Get the error message.
     *
     * @return string
     */
    public function getErrorMessage(): string
    {
        return curl_error($this->ch);
    }

    /**
     * Get the response status code.
     *
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->getInfo('http_code');
    }

    /**
     * Whether the request timeout
     *
     * @return bool
     */
    public function isTimeout(): bool
    {
        return $this->getErrorCode() === CURLE_OPERATION_TIMEOUTED;
    }

    /**
     * Whether the response is gzipd.
     *
     * @return bool
     */
    public function isGzipd(): bool
    {
        if ($this->isGzipd === null) {
            $this->isGzipd = 'gzip' === ($this->getHeader('Content-Encoding')[0] ?? null);
        }
        return $this->isGzipd;
    }

    /**
     * Get the response header of one line.
     * Not case-sensitive
     *
     * @param $header
     * @return string
     */
    public function getHeaderLine($header): string
    {
        return implode(', ', $this->getHeader($header));
    }

    /**
     * Get the response header.
     * Not case-sensitive
     *
     * @param $header
     * @return array
     */
    public function getHeader($header): array
    {
        $header = strtolower($header);

        if (!isset($this->headerNames[$header])) {
            return [];
        }

        $header = $this->headerNames[$header];

        return $this->headers[$header];
    }

    /**
     * Get the all response headers
     *
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Get the response content.
     *
     * @return string|bool|null
     */
    public function getContent(): string|bool|null
    {
        if ($this->isGzipd() && extension_loaded('zlib')) {
            return self::uncompress($this->content);
        }
        return $this->content;
    }

    /**
     * Set the response content.
     *
     * @param string|bool $content
     * @param bool $isGzipd
     * @return void
     */
    public function setContent(string|bool|null $content, bool $isGzipd = false): void
    {
        $this->content = $content;
        $this->isGzipd = $isGzipd;
    }

    /**
     * Get the response content as array.
     *
     * @throws JsonException
     */
    public function toArray()
    {
        if (!is_string($this->content)) {
            return [];
        }
        return json_decode($this->getContent(), true, 512, JSON_BIGINT_AS_STRING | JSON_THROW_ON_ERROR);
    }

    /**
     * Get the request object.
     *
     * @return Request
     */
    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getOptions(): Options
    {
        return $this->request->getOptions();
    }

    /**
     * Get the response infos.
     *
     * @param string|null $type
     * @param mixed|null $defaultValue
     * @return array
     */
    public function getInfo(string $type = null, mixed $defaultValue = null): mixed
    {
        if (empty($this->info)) {
            $request = $this->request;
            $this->info = array_merge([
                'http_code' => 0,
                'error' => null,
                'http_method' => $request->getOptions()->getMethod(),
                'retry_count' => $request->getOptions()->getRetryCount(),
            ], curl_getinfo($this->ch));
        }
        return $type !== null ? $this->info[$type] ?? $defaultValue : $this->info;
    }

    protected function addResponseHeader($header): void
    {
        $header = trim($header, "\r\n");
        if ($header) {
            if (str_starts_with($header, 'HTTP/')) {
                // Save the response cookie for the redirect
                if (isset($this->headerNames['set-cookie'])) {
                    $key = $this->headerNames['set-cookie'];
                    $this->headerNames = [$key => $key];
                    $this->headers = [$key => $this->headers[$key]];
                } else {
                    $this->headerNames = $this->headers = [];
                }
            } elseif (str_contains($header, ':')) {
                [$key, $value] = explode(':', $header, 2);
                $this->headerNames[strtolower($key)] = $key;
                $this->headers[$key][] = trim($value, " \t");
            }
        }
    }

    /**
     * Uncompress the content.
     *
     * @param string $data
     * @return string
     * @noinspection PhpFullyQualifiedNameUsageInspection
     * @noinspection PhpComposerExtensionStubsInspection
     */
    protected static function uncompress(string $data): string
    {
        $inflate = \inflate_init(\ZLIB_ENCODING_GZIP) ?: null;
        if (!$inflate) return $data;
        $index = 0;
        $result = '';
        $chunkSize = 8192;
        while (true) {
            $chunk = substr($data, $index, $chunkSize);
            if (!$chunk) {
                break;
            }
            $decodeChunk = @\inflate_add($inflate, $chunk);
            if (false === $decodeChunk) {
                throw new UnexpectedValueException('Failed to inflate data.');
            }
            $result .= $decodeChunk;
            $index += $chunkSize;
        }
        return $result;
    }
}
