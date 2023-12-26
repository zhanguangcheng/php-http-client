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
use function curl_close;
use function curl_errno;
use function curl_exec;
use function curl_getinfo;
use function curl_multi_getcontent;
use function curl_reset;
use function curl_setopt;
use function fclose;
use function implode;
use function is_resource;
use function strlen;
use const CURLE_OPERATION_TIMEOUTED;
use const CURLINFO_HEADER_OUT;
use const CURLINFO_HTTP_CODE;
use const CURLOPT_AUTOREFERER;
use const CURLOPT_CAINFO;
use const CURLOPT_COOKIE;
use const CURLOPT_CUSTOMREQUEST;
use const CURLOPT_FILE;
use const CURLOPT_FOLLOWLOCATION;
use const CURLOPT_HEADERFUNCTION;
use const CURLOPT_HTTPGET;
use const CURLOPT_HTTPHEADER;
use const CURLOPT_INFILE;
use const CURLOPT_MAXREDIRS;
use const CURLOPT_NOBODY;
use const CURLOPT_NOPROGRESS;
use const CURLOPT_POST;
use const CURLOPT_POSTFIELDS;
use const CURLOPT_PROGRESSFUNCTION;
use const CURLOPT_PROXY;
use const CURLOPT_REFERER;
use const CURLOPT_RETURNTRANSFER;
use const CURLOPT_SSL_VERIFYHOST;
use const CURLOPT_SSL_VERIFYPEER;
use const CURLOPT_TCP_NODELAY;
use const CURLOPT_TIMEOUT;
use const CURLOPT_URL;
use const CURLOPT_USERAGENT;
use const CURLOPT_USERPWD;

/**
 * Class Request
 */
class Request
{
    protected CurlHandle|false|null $ch = null;
    protected array $responseHeaders = [];
    protected Options $options;

    /**
     * @param Options $options
     */
    public function __construct(Options $options)
    {
        $this->options = $options;
        $this->reset();
    }

    public function __destruct()
    {
        $this->close();
    }

    /**
     * Get the request options object.
     *
     * @return Options
     */
    public function getOptions(): Options
    {
        return $this->options;
    }

    /**
     * Get the request handle.
     *
     * @return CurlHandle|false|null
     */
    public function getHandle(): CurlHandle|false|null
    {
        return $this->ch;
    }

    /**
     * Reset the request handle and options.
     *
     * @return void
     */
    public function reset(): void
    {
        if ($this->ch) {
            curl_reset($this->ch);
        } else {
            $this->ch = curl_init();
        }
    }

    /**
     * Close the request handle.
     *
     * @return void
     */
    public function close(): void
    {
        $this->end();
        if ($this->ch instanceof CurlHandle) {
            curl_close($this->ch);
        }
        $this->ch = null;
    }

    /**
     * Send request or get the multi-request response object
     *
     * @param bool $multiple
     * @return Response
     */
    public function send(bool $multiple): Response
    {
        $content = $multiple ? curl_multi_getcontent($this->ch) : curl_exec($this->ch);
        return new Response($this, $content, $this->responseHeaders);
    }

    /**
     * callback of request end.
     *
     * @param Response|null $response
     * @return void
     */
    public function end(?Response $response = null): void
    {
        if ($response && $this->options->getCookieJar()) {
            $this->options->getCookieJar()->saveCookies($response->getHeader('set-cookie'));
        }
        $fileHandle = $this->options->getCurlOption(CURLOPT_FILE);
        if ($fileHandle && is_resource($fileHandle)) {
            fclose($fileHandle);
            $this->options->setCurlOption(CURLOPT_FILE, null);
        }
        if (is_resource($this->options->getBody())) {
            fclose($this->options->getBody());
            $this->options->setBody(null);
        }
    }

    public function canRetry(): bool
    {
        if ($this->options->getRetryCount() >= $this->options->getMaxRetry()) {
            return false;
        }
        $errorCode = curl_errno($this->ch);
        $isTimeout = $errorCode === CURLE_OPERATION_TIMEOUTED;
        if ($isTimeout) {
            return true;
        }
        $statusCode = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
        $retryableStatusCodes = [423, 425, 429, 500, 502, 503, 504, 507, 510];
        return in_array($statusCode, $retryableStatusCodes, true) || $errorCode === 0 && $statusCode === 0;
    }

    /**
     * Apply request options.
     *
     * @return void
     * @throws JsonException
     */
    public function applyOptions(): void
    {
        $options = $this->options;
        $curlOptions = [
            CURLOPT_URL => $options->getFullUrl(),
            CURLOPT_TCP_NODELAY => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLINFO_HEADER_OUT => true,
            CURLOPT_AUTOREFERER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_COOKIESESSION => true,
            CURLOPT_MAXREDIRS => $options->getMaxRedirects(),
            CURLOPT_TIMEOUT => $options->getTimeout(),
            CURLOPT_SSL_VERIFYPEER => $options->isVerifyPeer(),
            CURLOPT_SSL_VERIFYHOST => $options->isVerifyHost() ? 2 : 0,
            CURLOPT_CAINFO => $options->getCafile(),
            CURLOPT_USERAGENT => $options->getUserAgent(),
            CURLOPT_REFERER => $options->getReferer(),
            CURLOPT_PROXY => $options->getProxy(),
            CURLOPT_HEADERFUNCTION => [$this, 'addResponseHeader'],
        ];
        if ($options->getContentType()) {
            $options->setHeader('Content-Type', $options->getContentType());
        }
        if ($options->getHeaders()) {
            $curlOptions[CURLOPT_HTTPHEADER] = $options->getHeaders(true);
        }
        if ($options->getCookies() || $options->getCookieJar()) {
            $cookies = array_merge($options->getCookieJar() ? $options->getCookieJar()->getCookies() : [], $options->getCookies());
            $cookieString = '';
            foreach ($cookies as $cookie) {
                $cookieString .= $cookie . '; ';
            }
            $curlOptions[CURLOPT_COOKIE] = $cookieString;
        }
        if ($options->getAuthBasic()) {
            $curlOptions[CURLOPT_USERPWD] = implode(':', $options->getAuthBasic());
        }
        match ($options->getMethod()) {
            'GET' => $curlOptions[CURLOPT_HTTPGET] = true,
            'POST' => $curlOptions[CURLOPT_POST] = true,
            'HEAD' => $curlOptions[CURLOPT_NOBODY] = true,
            default => $curlOptions[CURLOPT_CUSTOMREQUEST] = $options->getMethod(),
        };
        if ($options->getBody()) {
            if (is_resource($options->getBody())) {
                $curlOptions[CURLOPT_INFILE] = $options->getBody();
            } elseif ($options->getMethod() !== 'GET') {
                $curlOptions[CURLOPT_POSTFIELDS] = $options->getEncodeBody();
            }
        }
        if ($options->getOnProgress()) {
            $curlOptions[CURLOPT_NOPROGRESS] = false;
            $curlOptions[CURLOPT_PROGRESSFUNCTION] = $options->getOnProgress();
        }
        $curlOptions = $curlOptions + $options->getCurlOptions();
        foreach ($curlOptions as $option => $value) {
            if (null !== $value) {
                curl_setopt($this->ch, $option, $value);
            }
        }
    }

    /**
     * @param $ch
     * @param $header
     * @return int
     * @noinspection PhpUnusedParameterInspection
     */
    public function addResponseHeader($ch, $header): int
    {
        $this->responseHeaders[] = $header;
        return strlen($header);
    }

}
