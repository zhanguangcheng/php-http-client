<?php

/**
 * This file is part of the HttpClient package.
 *
 * @author  zhanguangcheng<14712905@qq.com>
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace Zane\HttpClient;

use CurlMultiHandle;
use Generator;
use InvalidArgumentException;
use JsonException;
use LogicException;
use function array_shift;
use function count;
use function curl_multi_add_handle;
use function curl_multi_close;
use function curl_multi_exec;
use function curl_multi_init;
use function curl_multi_remove_handle;
use function curl_multi_select;
use function extension_loaded;
use function fopen;
use function max;
use function min;
use function usleep;
use const CURLM_OK;
use const CURLOPT_FILE;

/**
 * Class HttpClient
 */
class HttpClient
{
    const VERSION = '0.1.0';

    /**
     * The number of concurrent requests.
     *
     * @var int|mixed
     */
    protected int $concurrency = 6;

    /**
     * Request pool.
     *
     * @var Request[]
     */
    protected array $requestPool = [];

    /**
     * Request queue.
     *
     * @var array
     */
    protected array $requestQueue = [];

    /**
     * Default request options.
     *
     * @var array
     */
    protected array $options = [];

    /**
     * @var CurlMultiHandle|false|null
     */
    protected CurlMultiHandle|false|null $mh = null;

    /**
     * Create a http client instance
     *
     * @param array $options The default request options.
     * @param $concurrency int The number of concurrent requests.
     */
    public static function create(array $options = [], int $concurrency = 6): static
    {
        return new static($options, max(1, $concurrency));
    }

    /**
     * @see static::create()
     */
    public function __construct(array $options = [], int $concurrency = 6)
    {
        extension_loaded('curl') || throw new LogicException('You cannot use the "Zane\HttpClient\HttpClient" as the "curl" extension is not installed.');
        $this->options = $options;
        $this->concurrency = $concurrency;
    }

    /**
     * Send a GET request.
     *
     * @param string $url The request URL.
     * @param array $query The query string parameters.
     * @param array $options The request options.
     * @return Response
     * @throws JsonException
     */
    public function get(string $url, array $query = [], array $options = []): Response
    {
        if ($query) {
            $options[Options::QUERY] = $query;
        }
        return $this->request('GET', $url, $options);
    }

    /**
     * Send a POST request.
     *
     * @param string $url The request URL.
     * @param mixed $body The request body.
     * @param array $options The request options.
     * @return Response
     * @throws JsonException
     */
    public function post(string $url, mixed $body = [], array $options = []): Response
    {
        if ($body) $options[Options::BODY] = $body;
        return $this->request('POST', $url, $options);
    }

    /**
     * Send a PUT request.
     *
     * @throws JsonException
     * @see self::post()
     */
    public function put(string $url, mixed $body = [], array $options = []): Response
    {
        if ($body) $options[Options::BODY] = $body;
        return $this->request('PUT', $url, $options);
    }

    /**
     * Send a PATCH request.
     *
     * @throws JsonException
     * @see self::post()
     */
    public function patch(string $url, mixed $body = [], array $options = []): Response
    {
        if ($body) $options[Options::BODY] = $body;
        return $this->request('PATCH', $url, $options);
    }

    /**
     * Send a DELETE request.
     *
     * @throws JsonException
     * @see self::post()
     */
    public function delete(string $url, mixed $body = [], array $options = []): Response
    {
        if ($body) $options[Options::BODY] = $body;
        return $this->request('DELETE', $url, $options);
    }

    /**
     * Download a file.
     *
     * @param string $url The download URL.
     * @param string $filename The save of path to file.
     * @param array $options The request options.
     * @return Response
     * @throws JsonException
     */
    public function download(string $url, string $filename, array $options = []): Response
    {
        $method = $options[Options::METHOD] ?? 'GET';
        $options[Options::CURL_OPTIONS][CURLOPT_FILE] = fopen($filename, 'wb');
        if (!isset($options[Options::TIMEOUT])) {
            $options[Options::TIMEOUT] = 0;
        }
        return $this->request($method, $url, $options);
    }

    /**
     * Send a request.
     *
     * @param string $method The request method.
     * @param string $url The request URL.
     * @param array $options The request options.
     * @return Response
     * @throws InvalidArgumentException
     * @throws JsonException
     */
    public function request(string $method, string $url, array $options = []): Response
    {
        $options[Options::METHOD] = $method;
        $options[Options::URL] = $url;
        $request = $this->getRequest($options);
        $response = $request->send(false);

        // Retry a failed request
        if ($request->canRetry()) {
            $options[Options::RETRY_COUNT] = $request->getOptions()->getRetryCount() + 1;
            if ($request->getOptions()->getTimeout() > 0) {
                // Limit retry timeout, first retry 1s, second retry 3s, third retry 5s
                $options[Options::TIMEOUT] = min(5, $options[Options::RETRY_COUNT] * 2 - 1);
            }
            return $this->request($method, $url, $options);
        }
        $request->end($response);
        return $response;
    }

    /**
     * Add a request to the request queue.
     *
     * @param string $method The request method.
     * @param string $url The request URL.
     * @param array $options The request options.
     * @return void
     */
    public function addRequest(string $method, string $url, array $options = []): void
    {
        $options[Options::METHOD] = $method;
        $options[Options::URL] = $url;
        $this->requestQueue[] = $options;
    }

    /**
     * Send the multiple-request in the queue
     *
     * @throws JsonException
     */
    public function send(): Generator
    {
        $this->mh = curl_multi_init();
        while (count($this->requestQueue) > 0) {
            $requests = $this->addRequestQueue();
            $failed = $this->execRequestQueue($requests);
            $failed && $this->retryRequest($requests, $failed);
            foreach ($requests as $request) {
                $response = $request->send(true);
                $request->end($response);
                yield $response;
            }
        }
        curl_multi_close($this->mh);
        $this->mh = null;
    }

    /**
     * Add requests to the request queue.
     *
     * @return Request[]
     * @throws JsonException
     */
    protected function addRequestQueue(): array
    {
        $requests = [];
        for ($i = 0; $i < $this->concurrency; $i++) {
            $options = array_shift($this->requestQueue);
            if (empty($options)) {
                break;
            }
            $request = $this->getRequest($options, $i);
            curl_multi_add_handle($this->mh, $request->getHandle());
            $requests[] = $request;
        }
        return $requests;
    }

    /**
     * Execute requests in the queue
     *
     * @param Request[] $requests The request queue.
     * @return array The failed request index.
     */
    protected function execRequestQueue(array $requests): array
    {
        do {
            $active = 0;
            $status = curl_multi_exec($this->mh, $active);
            if ($active) {
                // Wait for activity, wait a bit if error.
                curl_multi_select($this->mh, 0.5) === -1 && usleep(1000);
            }
        } while ($active && $status === CURLM_OK);

        $failed = [];
        foreach ($requests as $i => $request) {
            curl_multi_remove_handle($this->mh, $request->getHandle());
            if ($request->canRetry()) {
                $failed[] = $i;
            }
        }
        return $failed;
    }

    /**
     * Retry the failed multiple-request
     *
     * @param Request[] $requests
     * @param array $failed
     * @return void
     */
    protected function retryRequest(array $requests, array $failed): void
    {
        if (empty($failed)) return;
        foreach ($failed as $i) {
            $requests[$i]->getOptions()->incRetryCount();
            curl_multi_add_handle($this->mh, $requests[$i]->getHandle());
        }
        $failed = $this->execRequestQueue($requests);
        $failed && $this->retryRequest($requests, $failed);
    }

    /**
     * Get a request from the request pool.
     * If the request does not exist, a new request will be created.
     *
     * @param array $options The request options.
     * @param int $index The request index.
     * @return ?Request
     * @throws JsonException
     */
    protected function getRequest(array $options = [], int $index = 0): ?Request
    {
        $options = Options::mergeOptions($this->options, $options);
        if (isset($this->requestPool[$index])) {
            $request = $this->requestPool[$index];
            $request->reset();
            $request->getOptions()->reset()->applyOptions($options);
        } else {
            $request = new Request(new Options($options));
            $this->requestPool[$index] = $request;
        }
        $request->applyOptions();
        return $request;
    }

}
