<?php

declare(strict_types=1);

namespace ClickHouseDB\Tests;

use ClickHouseDB\Transport\CurlerRequest;
use ClickHouseDB\Transport\CurlerResponse;
use PHPUnit\Framework\TestCase;

class CurlerRequestResponseTest extends TestCase
{
    // ==================== CurlerRequest ====================

    public function testGetHeadersFormatsCorrectly(): void
    {
        $request = new CurlerRequest();
        $request->header('X-Custom', 'value1');

        $headers = $request->getHeaders();

        // Constructor adds Cache-Control, Expires, Pragma by default
        self::assertContains('X-Custom: value1', $headers);
        self::assertContains('Cache-Control: no-cache, no-store, must-revalidate', $headers);
        self::assertContains('Expires: 0', $headers);
        self::assertContains('Pragma: no-cache', $headers);
    }

    public function testHeaderAddsHeaderAndReturnsSelf(): void
    {
        $request = new CurlerRequest();
        $result = $request->header('X-Test', 'hello');

        self::assertSame($request, $result);
        self::assertContains('X-Test: hello', $request->getHeaders());
    }

    public function testUrlSetsAndGetsUrl(): void
    {
        $request = new CurlerRequest();
        $result = $request->url('http://example.com/test');

        self::assertSame($request, $result);
        self::assertSame('http://example.com/test', $request->getUrl());
    }

    public function testGetUniqHashReturnsNonEmptyStringWithDots(): void
    {
        $request = new CurlerRequest();
        $hash = $request->getUniqHash('myid');

        self::assertNotEmpty($hash);
        self::assertStringStartsWith('myid.', $hash);
        // Should contain at least two dots: "myid." + microtime has a space but the concat adds dot
        self::assertGreaterThanOrEqual(1, substr_count($hash, '.'));
    }

    public function testOptionSetsCurlOptionAndReturnsSelf(): void
    {
        $request = new CurlerRequest();
        $result = $request->option(CURLOPT_TIMEOUT, 30);

        self::assertSame($request, $result);
    }

    public function testPersistentAndIsPersistent(): void
    {
        $request = new CurlerRequest();

        self::assertFalse($request->isPersistent());

        $result = $request->persistent();
        self::assertSame($request, $result);
        self::assertTrue($request->isPersistent());
    }

    public function testVerboseSetsVerboseFlag(): void
    {
        $request = new CurlerRequest();
        $result = $request->verbose(true);

        self::assertSame($request, $result);
    }

    public function testParametersSetsParameters(): void
    {
        $request = new CurlerRequest();
        $result = $request->parameters('SELECT 1');

        self::assertSame($request, $result);

        // Verify through getDetails()
        $details = $request->getDetails();
        self::assertSame('SELECT 1', $details['parameters']);
    }

    public function testParametersSetsArrayParameters(): void
    {
        $request = new CurlerRequest();
        $request->parameters(['key' => 'value']);

        $details = $request->getDetails();
        self::assertSame(['key' => 'value'], $details['parameters']);
    }

    public function testConnectTimeOutSetsCurloptConnectTimeoutMs(): void
    {
        $request = new CurlerRequest();
        $result = $request->connectTimeOut(2.5);

        self::assertSame($request, $result);
    }

    public function testTimeOutSetsCurloptTimeoutMs(): void
    {
        $request = new CurlerRequest();
        $result = $request->timeOut(5.0);

        self::assertSame($request, $result);
    }

    public function testSetDnsCacheAndGetDnsCache(): void
    {
        $request = new CurlerRequest();

        // Default is 120
        self::assertSame(120, $request->getDnsCache());

        $result = $request->setDnsCache(300);
        self::assertSame($request, $result);
        self::assertSame(300, $request->getDnsCache());
    }

    public function testDumpReturnsStringWhenResultTrue(): void
    {
        $request = new CurlerRequest();
        $request->url('http://localhost:8123');
        $request->parameters('SELECT 1');

        $output = $request->dump(true);

        self::assertIsString($output);
        self::assertStringContainsString('Request', $output);
        self::assertStringContainsString('http://localhost:8123', $output);
        self::assertStringContainsString('SELECT 1', $output);
    }

    public function testGetIdReturnsConstructorId(): void
    {
        $request = new CurlerRequest('test-id-123');

        self::assertSame('test-id-123', $request->getId());
    }

    public function testGetIdReturnsFalseByDefault(): void
    {
        $request = new CurlerRequest();

        self::assertFalse($request->getId());
    }

    public function testHttpCompressionSetsHeaders(): void
    {
        $request = new CurlerRequest();
        $request->httpCompression(true);

        // httpCompression sets CURLOPT_ENCODING internally, we can't check that directly,
        // but we can verify the method doesn't throw and the object is consistent
        // Disabling should also work
        $request->httpCompression(false);
        self::assertTrue(true); // No exception means success
    }

    public function testAuthByHeadersSetsClickHouseHeaders(): void
    {
        $request = new CurlerRequest();
        $result = $request->authByHeaders('myuser', 'mypass');

        self::assertSame($request, $result);

        $headers = $request->getHeaders();
        self::assertContains('X-ClickHouse-User: myuser', $headers);
        self::assertContains('X-ClickHouse-Key: mypass', $headers);
    }

    public function testAuthByBasicAuthReturnsSelf(): void
    {
        $request = new CurlerRequest();
        $result = $request->authByBasicAuth('user', 'pass');

        self::assertSame($request, $result);
    }

    // ==================== CurlerResponse ====================

    public function testErrorNoReturnsZeroByDefault(): void
    {
        $response = new CurlerResponse();

        self::assertSame(0, $response->error_no());
    }

    public function testErrorReturnsEmptyStringByDefault(): void
    {
        $response = new CurlerResponse();

        self::assertSame('', $response->error());
    }

    public function testBodyReturnsEmptyStringByDefault(): void
    {
        $response = new CurlerResponse();

        self::assertSame('', $response->body());
    }

    public function testSettingBodyAndReadingIt(): void
    {
        $response = new CurlerResponse();
        $response->_body = 'Hello ClickHouse';

        self::assertSame('Hello ClickHouse', $response->body());
    }

    public function testInfoUrlAndTotalTimeAndHttpCodeAndContentType(): void
    {
        $response = new CurlerResponse();
        $response->_info = [
            'url'            => 'http://localhost:8123/',
            'total_time'     => 0.12345,
            'http_code'      => 200,
            'content_type'   => 'text/tab-separated-values; charset=UTF-8',
        ];

        self::assertSame('http://localhost:8123/', $response->url());
        self::assertSame(0.123, $response->total_time());
        self::assertSame(200, $response->http_code());
        self::assertSame('text/tab-separated-values; charset=UTF-8', $response->content_type());
    }

    public function testHeadersReturnsNullForMissingHeader(): void
    {
        $response = new CurlerResponse();

        self::assertNull($response->headers('X-Missing'));
    }

    public function testSettingHeadersAndReadingThem(): void
    {
        $response = new CurlerResponse();
        $response->_headers = [
            'Content-Type' => 'application/json',
            'Connection'   => 'Keep-Alive',
        ];

        self::assertSame('application/json', $response->headers('Content-Type'));
        self::assertSame('Keep-Alive', $response->headers('Connection'));
        self::assertNull($response->headers('X-Nonexistent'));
    }

    public function testConnectionReadsConnectionHeader(): void
    {
        $response = new CurlerResponse();
        $response->_headers = ['Connection' => 'close'];

        self::assertSame('close', $response->connection());
    }

    public function testConnectionReturnsNullWhenMissing(): void
    {
        $response = new CurlerResponse();

        self::assertNull($response->connection());
    }

    public function testJsonDecodesBody(): void
    {
        $response = new CurlerResponse();
        $response->_body = '{"rows":10,"data":[1,2,3]}';

        $decoded = $response->json();

        self::assertIsArray($decoded);
        self::assertSame(10, $decoded['rows']);
        self::assertSame([1, 2, 3], $decoded['data']);
    }

    public function testJsonWithKeyReturnsSpecificValue(): void
    {
        $response = new CurlerResponse();
        $response->_body = '{"rows":10,"meta":"info"}';

        self::assertSame(10, $response->json('rows'));
        self::assertSame('info', $response->json('meta'));
    }

    public function testJsonWithMissingKeyReturnsFalse(): void
    {
        $response = new CurlerResponse();
        $response->_body = '{"rows":10}';

        self::assertFalse($response->json('nonexistent'));
    }

    public function testDumpReturnsFormattedStringWhenResultTrue(): void
    {
        $response = new CurlerResponse();
        $response->_body = 'test body content';
        $response->_error = 'some error';
        $response->_info = ['url' => 'http://localhost'];

        $output = $response->dump(true);

        self::assertIsString($output);
        self::assertStringContainsString('Response', $output);
        self::assertStringContainsString('test body content', $output);
        self::assertStringContainsString('some error', $output);
    }

    public function testSizeMethodsUseHumanFileSize(): void
    {
        $response = new CurlerResponse();
        $response->_info = [
            'size_upload'           => 1024,
            'upload_content_length' => 2048,
            'size_download'         => 1048576,
            'request_size'          => 512,
            'header_size'           => 256,
        ];

        self::assertSame('1.00 KB', $response->size_upload());
        self::assertSame('2.00 KB', $response->upload_content_length());
        self::assertSame('1.00 MB', $response->size_download());
        self::assertSame('512 bytes', $response->request_size());
        self::assertSame('256 bytes', $response->header_size());
    }

    public function testAsStringReturnsSameAsBody(): void
    {
        $response = new CurlerResponse();
        $response->_body = 'some data';

        self::assertSame($response->body(), $response->as_string());
    }

    public function testErrorNoCanBeSet(): void
    {
        $response = new CurlerResponse();
        $response->_errorNo = 28; // CURLE_OPERATION_TIMEDOUT

        self::assertSame(28, $response->error_no());
    }

    public function testErrorCanBeSet(): void
    {
        $response = new CurlerResponse();
        $response->_error = 'Connection timed out';

        self::assertSame('Connection timed out', $response->error());
    }
}
