<?php /** @noinspection PhpUnhandledExceptionInspection */

use Zane\HttpClient\CookieJar;
use Zane\HttpClient\HttpClient;
use Zane\HttpClient\Options;

class HttpClientTest extends PHPUnit\Framework\TestCase
{
    protected static HttpClient $client;

    public static function setUpBeforeClass(): void
    {
        self::$client = HttpClient::create([
            Options::BASE_URL => 'https://httpbin.org',
        ]);
    }

    public function testCreateClient()
    {
        $client = HttpClient::create();
        $this->assertInstanceOf(HttpClient::class, $client);
    }

    public function testGet()
    {
        $response = self::$client->get('/get?a=b', ['c' => 'd']);
        $this->assertEquals(['a' => 'b', 'c' => 'd'], $response->toArray()['args']);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(0, $response->getErrorCode());
        $this->assertEquals('', $response->getErrorMessage());
    }

    public function testPost()
    {
        $response = self::$client->post('/post', ['a' => 'b', 'c' => 'd']);
        $this->assertEquals(['a' => 'b', 'c' => 'd'], $response->toArray()['form']);
    }

    public function testPostFile()
    {
        $response = self::$client->post('/post', [
            'file' => new CURLFile(__DIR__ . '/test.file'),
        ]);
        $this->assertEquals('text', $response->toArray()['files']['file']);
    }

    public function testPostRawBody()
    {
        $response = self::$client->post('/post', 'raw text');
        $this->assertEquals('raw text', $response->toArray()['data']);
    }

    public function testPostJson()
    {
        $response = self::$client->post('/post', '{"name": "Zane"}');
        $this->assertEquals(['name' => 'Zane'], $response->toArray()['json']);
    }

    public function testPostXml()
    {
        $response = self::$client->post('/post', '<root><name>Zane</name></root>');
        $this->assertEquals('application/xml', $response->toArray()['headers']['Content-Type']);
    }

    public function testPostRawFile()
    {
        $response = self::$client->post('/post', fopen(__DIR__ . '/test.file', 'r'));
        $this->assertEquals('text', $response->toArray()['data']);
    }

    public function testPut()
    {
        $response = self::$client->put('/put', ['a' => 'b', 'c' => 'd']);
        $this->assertEquals(['a' => 'b', 'c' => 'd'], $response->toArray()['form']);
    }

    public function testPatch()
    {
        $response = self::$client->patch('/patch', ['a' => 'b', 'c' => 'd']);
        $this->assertEquals(['a' => 'b', 'c' => 'd'], $response->toArray()['form']);
    }

    public function testDelete()
    {
        $response = self::$client->delete('/delete', ['a' => 'b', 'c' => 'd']);
        $this->assertEquals(['a' => 'b', 'c' => 'd'], $response->toArray()['form']);
    }

    public function testDownload()
    {
        $response = self::$client->download('/get', __DIR__ . '/testdownload.json', [
            Options::QUERY => ['a' => 'b']
        ]);
        $this->assertTrue($response->getContent());
        $this->assertEquals([], $response->toArray());

        $json = json_decode(file_get_contents(__DIR__ . '/testdownload.json'), true);
        $this->assertEquals('b', $json['args']['a']);
    }

    public function testAuthBasic()
    {
        $response = self::$client->get('/basic-auth/user/passwd', [], [
            Options::AUTH_BASIC => ['user', 'passwd']
        ]);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($response->toArray()['authenticated']);
        $this->assertEquals('user', $response->toArray()['user']);
    }

    public function testAuthBearer()
    {
        $response = self::$client->get('/bearer', [], [
            Options::AUTH_BEARER => 'hello'
        ]);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($response->toArray()['authenticated']);
        $this->assertEquals('hello', $response->toArray()['token']);
    }

    public function testStatusCode()
    {
        $response = self::$client->get('/status/200');
        $this->assertEquals(200, $response->getStatusCode());
        $response = self::$client->get('/status/404');
        $this->assertEquals(404, $response->getStatusCode());
        $response = self::$client->post('/status/500', [], [Options::MAX_RETRY => 0]);
        $this->assertEquals(500, $response->getStatusCode());
    }

    public function testUserAgent()
    {
        $response = self::$client->get('/user-agent');
        $options = Options::getDefault();
        $this->assertEquals($options[Options::USER_AGENT], $response->toArray()['user-agent']);
    }

    public function testHeaders()
    {
        $response = self::$client->get('/headers', [], [
            Options::CONTENT_TYPE => Options::TYPE_JSON,
            Options::REFERER => 'test',
            Options::HEADERS => [
                'hello-world' => 'val',
                'x-custom' => 'aaa',
                'Cookie' => 'a=b',
            ]
        ]);
        $headers = $response->toArray()['headers'];
        $this->assertEquals('application/json', $headers['Content-Type']);
        $this->assertEquals('a=b', $headers['Cookie']);
        $this->assertEquals('val', $headers['Hello-World']);
        $this->assertEquals('test', $headers['Referer']);
        $this->assertEquals('aaa', $headers['X-Custom']);
    }

    public function testResponseHeader()
    {
        $response = self::$client->get('/response-headers', [
            'hello-world' => 'val',
            'x-custom' => 'aaa',
        ]);
        $this->assertEquals('val', $response->getHeaderLine('Hello-World'));
        $this->assertEquals('aaa', $response->getHeader('X-Custom')[0]);
        $this->assertEquals('aaa', $response->getHeaders()['x-custom'][0]);
    }

    public function testResponseTypeJson()
    {
        $response = self::$client->get('/json');
        $this->assertTrue(is_array($response->toArray()));
    }

    public function testResponseTypeGzip()
    {
        $response = self::$client->get('/gzip');
        $this->assertTrue(is_array($response->toArray()));
        $this->assertTrue($response->toArray()['gzipped']);
    }

    /**
     * @throws JsonException
     */
    public function testGetUrl()
    {
        $response = self::$client->get('/get?a=b', [
            'c' => 'd',
        ]);
        $options = $response->getOptions();
        $baseUrl = $options->getBaseUrl();
        $this->assertEquals("$baseUrl/get?a=b&c=d", $options->getFullUrl());
    }

    public function testRequestHeaders()
    {
        $response = self::$client->get('/get', [], [
            Options::HEADERS => [
                'a' => 'b',
            ],
        ]);
        $this->assertEquals('b', $response->toArray()['headers']['A']);
    }

    public function testRequestCookies()
    {
        $response = self::$client->get('/get', [], [
            Options::COOKIES => [
                "a=b"
            ],
        ]);
        $this->assertStringContainsString('a=b', $response->toArray()['headers']['Cookie']);
    }

    public function testCookieJar()
    {
        $cookieJar = new CookieJar();
        self::$client->get('/cookies/set', ['a' => 'b'], [
            Options::COOKIE_JAR => $cookieJar,
        ]);
        $response = self::$client->get('/cookies', [], [
            Options::COOKIE_JAR => $cookieJar,
        ]);
        $this->assertStringContainsString('a=b', $cookieJar->getCookies()[0]);
        $this->assertEquals(['a' => 'b'], $response->toArray()['cookies']);
    }

    public function testContentType()
    {
        $response = self::$client->post('/post', ['a' => 'b'], [
            Options::CONTENT_TYPE => Options::TYPE_URL_ENCODED,
        ]);
        $this->assertEquals('application/x-www-form-urlencoded', $response->toArray()['headers']['Content-Type']);
        $this->assertEquals(['a' => 'b'], $response->toArray()['form']);

        $response = self::$client->post('/post', ['a' => 'b'], [
            Options::CONTENT_TYPE => Options::TYPE_JSON,
        ]);
        $this->assertEquals('application/json', $response->toArray()['headers']['Content-Type']);
        $this->assertEquals(['a' => 'b'], $response->toArray()['json']);

        $response = self::$client->post('/post', ['a' => 'b'], [
            Options::CONTENT_TYPE => Options::TYPE_FORM_DATA,
        ]);
        $this->assertStringStartsWith('multipart/form-data', $response->toArray()['headers']['Content-Type']);
        $this->assertEquals(['a' => 'b'], $response->toArray()['form']);

        $response = self::$client->post('/post', '123', [
            Options::CONTENT_TYPE => Options::TYPE_TEXT,
        ]);
        $this->assertEquals('text/plain', $response->toArray()['headers']['Content-Type']);
        $this->assertEquals('123', $response->toArray()['data']);
    }

    public function testRetry()
    {
        $response = self::$client->get('/status/500', [], [
            Options::MAX_RETRY => 3
        ]);
        $options = $response->getOptions();
        $this->assertEquals(3, $options->getRetryCount());

        $response = self::$client->get('/status/404', [], [
            Options::MAX_RETRY => 3
        ]);
        $options = $response->getOptions();
        $this->assertEquals(0, $options->getRetryCount());
    }

    public function testMultipleRequest()
    {
        for ($i = 0; $i < 10; $i++) {
            self::$client->addRequest('GET', '/get', [
                Options::QUERY => ['i' => $i]
            ]);
        }
        $responses = self::$client->send();
        foreach ($responses as $i => $response) {
            $this->assertEquals(['i' => $i], $response->toArray()['args']);
        }
    }

    public function testMultipleRequestRetry()
    {
        for ($i = 0; $i < 2; $i++) {
            self::$client->addRequest('GET', '/status/500', [
                Options::QUERY => ['i' => $i]
            ]);
        }
        $responses = self::$client->send();
        foreach ($responses as $response) {
            $this->assertEquals(3, $response->getInfo('retry_count'));
        }
    }

    public function testOnProgress()
    {
        $done = false;
        $response = self::$client->get('/get', [], [
            Options::ON_PROGRESS => function ($ch, $downloadTotal, $downloaded) use(&$done) {
                $done = $downloadTotal && $downloadTotal === $downloaded;
            }
        ]);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($done);
    }

    public function testRequestOptions()
    {
        $options = [
            Options::BASE_URL => 'https://httpbin.org',
            Options::QUERY => ['p2' => 'v2'],
            Options::BODY => ['p3' => 'v3'],
            Options::TIMEOUT => 10,
            Options::AUTH_BASIC => ['user', 'passwd'],
            Options::AUTH_BEARER => 'token',
            Options::CONTENT_TYPE => Options::TYPE_JSON,
            Options::HEADERS => ['h1' => 'v1'],
            Options::COOKIES => ['c1' => 'v1'],
            Options::MAX_REDIRECTS => 5,
            Options::MAX_RETRY => 3,
            Options::USER_AGENT => 'test-ua',
            Options::REFERER => 'test-referer',
            Options::VERIFY_HOST => false,
            Options::VERIFY_PEER => false,
            Options::PROXY => null,
            Options::CAFILE => __DIR__ . '/test.file',
            Options::ACCEPT_GZIP => false,
            Options::ON_PROGRESS => function ($ch, $downloadTotal, $downloaded, $uploadTotal, $uploaded) {
                // echo "downloadTotal: $downloadTotal, downloaded: $downloaded, uploadTotal: $uploadTotal, uploaded: $uploaded\n";
            },
            Options::CURL_OPTIONS => [
                CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
            ]
        ];
        $option = new Options($options);
        $opts = $option->toArray();
        foreach ($options as $key => $value) {
            if ($key === Options::HEADERS) {
                $this->assertEquals($value['h1'], $opts['headers']['h1']);
                continue;
            }
            $this->assertEquals($value, $opts[$key]);
        }
    }

    public function testDeepMergeOptions()
    {
        $client = HttpClient::create(
            (new Options())
                ->setHeaders([
                    'h1' => 'v1',
                    'h2' => 'v2',
                ])->setCurlOptions([
                    CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
                ])->toArray()
        );
        $response = $client->get('/get', [], [
            Options::HEADERS => [
                'h1' => 'v3',
                'h3' => 'v3',
            ],
            Options::CURL_OPTIONS => [
                CURLOPT_CAINFO => __DIR__ . '/test.file',
            ]
        ]);
        $this->assertEquals([
            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
            CURLOPT_CAINFO => __DIR__ . '/test.file',
        ], $response->getOptions()->getCurlOptions());

        $headers = $response->getOptions()->getHeaders();
        $this->assertEquals('v3', $headers['h1']);
        $this->assertEquals('v2', $headers['h2']);
        $this->assertEquals('v3', $headers['h3']);
    }

    public function testCafileNotExists()
    {
        $this->expectException(InvalidArgumentException::class);
        self::$client->request('GET', '/get', [
            Options::CAFILE => __DIR__ . '/not-exists.file',
        ]);
    }

    public function testInvalidAuthBasic()
    {
        $this->expectException(InvalidArgumentException::class);
        self::$client->request('GET', '/get', [
            Options::AUTH_BASIC => ['user'],
        ]);
    }

    public function testInvalidOption()
    {
        $this->expectException(InvalidArgumentException::class);
        self::$client->request('GET', '/get', [
            'invalid' => 'option',
        ]);

        $this->expectException(InvalidArgumentException::class);
        self::$client->request('GET', '/get', [
            'timeout' => -1,
        ]);
    }

    public function testResponseGetId()
    {
        $response = self::$client->get('/get');
        $this->assertNotEmpty($response->getId());
    }

    public function testResponseError()
    {
        $response = self::$client->get('/status/500', [], [
            Options::MAX_RETRY => 0,
        ]);
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals(0, $response->getErrorCode());
    }

    public function testResponseIsTimeout()
    {
        $response = self::$client->get('/delay/6');
        $this->assertTrue($response->isTimeout());
    }

    public function testResponseSetContent()
    {
        $response = self::$client->get('/gzip');
        $response->setContent('test');
        $this->assertEquals('test', $response->getContent());

        $this->expectException(UnexpectedValueException::class);
        $response->setContent('test', true);
        $response->getContent();
    }

    public function testSetTimeoutError()
    {
        $this->expectException(InvalidArgumentException::class);
        self::$client->get('/get', [], [
            Options::TIMEOUT => -1,
        ]);
    }

    public function testSetMaxRedirectsError()
    {
        $this->expectException(InvalidArgumentException::class);
        self::$client->get('/get', [], [
            Options::MAX_REDIRECTS => -1,
        ]);
    }

    public function testMaxRedirects()
    {
        $response = self::$client->get('/redirect/3', [], [
            Options::MAX_REDIRECTS => 1,
        ]);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals(1, $response->getInfo('redirect_count'));
    }

    public function testClose()
    {
        $response = self::$client->get('/get');
        $request = $response->getRequest();
        $request->close();
        $this->assertNull($request->getHandle());
    }

    public function testDestroy()
    {
        $response = self::$client->get('/get');
        $response->getRequest()->__destruct();
        $this->assertNull($response->getRequest()->getHandle());
    }

}
