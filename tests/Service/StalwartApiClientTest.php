<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\StalwartApiClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;

#[CoversClass(StalwartApiClient::class)]
final class StalwartApiClientTest extends TestCase
{
    public function testGetSettingsReturnsItemsFromValidResponse(): void
    {
        $mockResponse = new MockResponse(json_encode([
            'data' => [
                'total' => 2,
                'items' => [
                    'queue.limiter.outbound.google.rate' => '100/1h',
                    'queue.limiter.outbound.google.concurrency' => '10',
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        $client = new StalwartApiClient(new MockHttpClient($mockResponse));

        $result = $client->getSettings('queue.limiter.outbound.google');

        self::assertSame([
            'queue.limiter.outbound.google.rate' => '100/1h',
            'queue.limiter.outbound.google.concurrency' => '10',
        ], $result);
    }

    public function testGetSettingsReturnsEmptyArrayWhenNoResults(): void
    {
        $mockResponse = new MockResponse(json_encode([
            'data' => [
                'total' => 0,
                'items' => [],
            ],
        ], JSON_THROW_ON_ERROR));

        $client = new StalwartApiClient(new MockHttpClient($mockResponse));

        $result = $client->getSettings('nonexistent.prefix');

        self::assertSame([], $result);
    }

    public function testGetSettingsSendsCorrectGetRequest(): void
    {
        $mockResponse = new MockResponse(json_encode([
            'data' => ['total' => 0, 'items' => []],
        ], JSON_THROW_ON_ERROR));

        $httpClient = new MockHttpClient($mockResponse);
        $client = new StalwartApiClient($httpClient);

        $client->getSettings('queue.limiter');

        self::assertSame('GET', $mockResponse->getRequestMethod());
        self::assertStringContainsString('/api/settings/list', $mockResponse->getRequestUrl());
        self::assertStringContainsString('prefix=queue.limiter', $mockResponse->getRequestUrl());
    }

    public function testSetSettingsSendsCorrectPostPayload(): void
    {
        $mockResponse = new MockResponse('{}');
        $httpClient = new MockHttpClient($mockResponse);
        $client = new StalwartApiClient($httpClient);

        $client->setSettings([
            'queue.limiter.outbound.google.rate' => '100/1h',
            'queue.limiter.outbound.google.concurrency' => '10',
        ]);

        self::assertSame('POST', $mockResponse->getRequestMethod());
        self::assertStringContainsString('/api/settings', $mockResponse->getRequestUrl());

        $body = json_decode($mockResponse->getRequestOptions()['body'], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame([
            [
                'type' => 'insert',
                'values' => [
                    ['queue.limiter.outbound.google.rate', '100/1h'],
                    ['queue.limiter.outbound.google.concurrency', '10'],
                ],
                'assert_empty' => false,
            ],
        ], $body);
    }

    public function testSetSettingsWithEmptyArrayIsNoop(): void
    {
        $httpClient = new MockHttpClient(function (): never {
            throw new \LogicException('No HTTP call should be made for empty settings.');
        });

        $client = new StalwartApiClient($httpClient);
        $client->setSettings([]);

        // If we reach here without exception, no HTTP call was made
        $this->addToAssertionCount(1);
    }

    public function testDeleteSettingsSendsDeleteWithCorrectPrefix(): void
    {
        $mockResponse = new MockResponse('{}');
        $httpClient = new MockHttpClient($mockResponse);
        $client = new StalwartApiClient($httpClient);

        $client->deleteSettings('queue.limiter.outbound.google');

        self::assertSame('DELETE', $mockResponse->getRequestMethod());
        self::assertStringContainsString('/api/settings/list', $mockResponse->getRequestUrl());
        self::assertStringContainsString('prefix=queue.limiter.outbound.google.', $mockResponse->getRequestUrl());
    }

    public function testDeleteSettingsDoesNotDoubleTrailingDot(): void
    {
        $mockResponse = new MockResponse('{}');
        $httpClient = new MockHttpClient($mockResponse);
        $client = new StalwartApiClient($httpClient);

        $client->deleteSettings('queue.limiter.outbound.google.');

        self::assertStringContainsString('prefix=queue.limiter.outbound.google.', $mockResponse->getRequestUrl());
        self::assertStringNotContainsString('prefix=queue.limiter.outbound.google..', $mockResponse->getRequestUrl());
    }

    public function testApplyRateLimitCallsDeleteThenSetWithCorrectKeys(): void
    {
        $requestIndex = 0;
        $responses = [
            new MockResponse('{}'), // DELETE
            new MockResponse('{}'), // POST
        ];

        $httpClient = new MockHttpClient(function (string $method, string $url) use (&$requestIndex, $responses): MockResponse {
            return $responses[$requestIndex++];
        });

        $client = new StalwartApiClient($httpClient);
        $client->applyRateLimit('google', ['gmail.com', 'googlemail.com'], '100/1h', 10);

        // Verify DELETE was called first
        self::assertSame('DELETE', $responses[0]->getRequestMethod());
        self::assertStringContainsString('prefix=queue.limiter.outbound.google.', $responses[0]->getRequestUrl());

        // Verify POST was called second
        self::assertSame('POST', $responses[1]->getRequestMethod());
        $body = json_decode($responses[1]->getRequestOptions()['body'], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame([
            [
                'type' => 'insert',
                'values' => [
                    ['queue.limiter.outbound.google.rate', '100/1h'],
                    ['queue.limiter.outbound.google.concurrency', '10'],
                    ['queue.limiter.outbound.google.match', 'gmail.com,googlemail.com'],
                ],
                'assert_empty' => false,
            ],
        ], $body);
    }

    public function testGetSettingsPropagatesHttpClientException(): void
    {
        $mockResponse = new MockResponse('Unauthorized', [
            'http_code' => 401,
        ]);

        $client = new StalwartApiClient(new MockHttpClient($mockResponse));

        $this->expectException(ClientExceptionInterface::class);
        $client->getSettings('some.prefix');
    }
}
